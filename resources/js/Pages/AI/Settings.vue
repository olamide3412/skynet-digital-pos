<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import axios from 'axios'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    settings:  Object,
    providers: Array,
    branches:  Array,
})

const form = useForm({
    active_provider:   props.settings.active_provider ?? 'gemini',
    api_key:           '',
    provider_models:   {},
    temperature:       props.settings.temperature ?? 0.7,
    max_tokens:        props.settings.max_tokens ?? 2048,
    is_system_enabled: props.settings.is_system_enabled !== false,
})

// Initialize provider models mapping
props.providers.forEach(p => {
    form.provider_models[p.id] = p.selected_model || p.default_model
})

const showApiKey = ref(false)
const testLoading = ref(false)
const testResult = ref(null)

const activeProviderConfig = computed(() => {
    return props.providers.find(p => p.id === form.active_provider) || props.providers[0]
})

function submit() {
    form.post(route('superadmin.ai.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.api_key = '' // Clear sensitive field after save
        },
    })
}

async function runTestConnection() {
    testLoading.value = true
    testResult.value = null

    try {
        const res = await axios.post(route('superadmin.ai.test-connection'), {
            provider: form.active_provider,
            api_key:  form.api_key || null,
            model:    form.provider_models[form.active_provider] || null,
        })
        testResult.value = res.data
    } catch (err) {
        testResult.value = {
            success: false,
            message: err.response?.data?.message || err.message || 'Connection test failed.',
            latency_ms: 0,
        }
    } finally {
        testLoading.value = false
    }
}

function toggleBranchAi(branch) {
    router.post(route('superadmin.ai.branches.toggle', { branch: branch.slug }), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xs">
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>✨</span>
                    <span>AI Engine & Multi-Provider Settings</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Configure and test multi-provider AI backends (Google Gemini, OpenAI, DeepSeek, Kimi, Claude) for reorder forecasting and natural language business insights.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span
                    :class="[
                        'px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1.5',
                        form.is_system_enabled
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                            : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                    ]"
                >
                    <span :class="form.is_system_enabled ? 'bg-emerald-500' : 'bg-rose-500'" class="w-2 h-2 rounded-full"></span>
                    {{ form.is_system_enabled ? 'System AI Active' : 'System AI Paused' }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- ── Left Column: Provider & Key Configuration (2 cols) ───────── -->
            <div class="lg:col-span-2 space-y-6">
                <form @submit.prevent="submit" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-6 shadow-xs">
                    <div class="border-b border-slate-200 dark:border-slate-700 pb-3 flex items-center justify-between">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            1. Select Active Provider
                        </h2>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.is_system_enabled" class="w-4 h-4 accent-emerald-500 rounded cursor-pointer" />
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Enable AI Features</span>
                        </label>
                    </div>

                    <!-- Provider Selection Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <label
                            v-for="provider in providers"
                            :key="provider.id"
                            @click="form.active_provider = provider.id"
                            :class="[
                                'p-3.5 rounded-xl border transition-all cursor-pointer flex flex-col justify-between relative',
                                form.active_provider === provider.id
                                    ? 'bg-emerald-50/70 dark:bg-emerald-950/40 border-emerald-500 dark:border-emerald-400 ring-2 ring-emerald-500/20 shadow-xs'
                                    : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                            ]"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-bold text-xs text-slate-900 dark:text-white">
                                    {{ provider.name }}
                                </span>
                                <input
                                    type="radio"
                                    v-model="form.active_provider"
                                    :value="provider.id"
                                    class="accent-emerald-600"
                                />
                            </div>

                            <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-200/60 dark:border-slate-600/60">
                                <span
                                    :class="provider.has_key ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500'"
                                    class="text-[10px] font-bold flex items-center gap-1"
                                >
                                    <span>{{ provider.has_key ? '●' : '○' }}</span>
                                    <span>{{ provider.has_key ? 'Key Saved' : 'No Key' }}</span>
                                </span>

                                <span class="text-[9px] font-mono text-slate-400 truncate max-w-[80px]">
                                    {{ form.provider_models[provider.id] || provider.default_model }}
                                </span>
                            </div>
                        </label>
                    </div>

                    <!-- Active Provider Configuration Details -->
                    <div class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Configuration for {{ activeProviderConfig.name }}
                            </span>
                            <span v-if="activeProviderConfig.has_key" class="text-[10px] bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold px-2 py-0.5 rounded-full border border-emerald-300 dark:border-emerald-700">
                                Encrypted Key Active
                            </span>
                        </div>

                        <!-- API Key Input -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                {{ activeProviderConfig.name }} API Key
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.api_key"
                                    :type="showApiKey ? 'text' : 'password'"
                                    :placeholder="activeProviderConfig.has_key ? '•••••••••••••••••••••••• (Leave blank to keep existing key)' : 'Enter your provider API key here...'"
                                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 pr-16 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-mono transition"
                                />
                                <button
                                    type="button"
                                    @click="showApiKey = !showApiKey"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 px-2 py-1 text-[10px] font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 cursor-pointer"
                                >
                                    {{ showApiKey ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Keys are securely encrypted with AES-256 before database storage.
                            </p>
                        </div>

                        <!-- Model Selection -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Model Selection
                            </label>
                            <select
                                v-model="form.provider_models[form.active_provider]"
                                class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs transition"
                            >
                                <option
                                    v-for="(label, modelKey) in activeProviderConfig.available_models"
                                    :key="modelKey"
                                    :value="modelKey"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Parameters (Temperature & Max Tokens) -->
                        <div class="grid grid-cols-2 gap-4 pt-1">
                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    <span>Temperature (Creativity)</span>
                                    <span class="font-mono text-emerald-600">{{ form.temperature }}</span>
                                </div>
                                <input
                                    v-model.number="form.temperature"
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.05"
                                    class="w-full accent-emerald-600"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Max Output Tokens
                                </label>
                                <input
                                    v-model.number="form.max_tokens"
                                    type="number"
                                    min="256"
                                    max="8192"
                                    step="256"
                                    class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none text-xs font-mono transition"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="flex items-center justify-between pt-2">
                        <button
                            type="button"
                            @click="runTestConnection"
                            :disabled="testLoading"
                            class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                        >
                            <span>{{ testLoading ? 'Testing...' : '⚡ Test Connection' }}</span>
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-900/20 transition disabled:opacity-40 cursor-pointer"
                        >
                            {{ form.processing ? 'Saving...' : 'Save AI Settings' }}
                        </button>
                    </div>

                    <!-- Live Test Diagnostic Result Box -->
                    <div
                        v-if="testResult"
                        :class="[
                            'p-4 rounded-xl border text-xs transition-all space-y-1',
                            testResult.success
                                ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-400 text-emerald-800 dark:text-emerald-200'
                                : 'bg-rose-50 dark:bg-rose-950/40 border-rose-400 text-rose-800 dark:text-rose-200'
                        ]"
                    >
                        <div class="flex items-center justify-between font-bold">
                            <span>{{ testResult.success ? '✓ Connection Verified' : '✕ Connection Test Failed' }}</span>
                            <span v-if="testResult.latency_ms > 0" class="font-mono text-[10px] opacity-80">
                                Latency: {{ testResult.latency_ms }}ms
                            </span>
                        </div>
                        <p class="text-[11px] opacity-90 leading-relaxed break-words">
                            {{ testResult.message }}
                        </p>
                    </div>
                </form>
            </div>

            <!-- ── Right Column: Per-Branch AI Enablement Matrix ─────────────── -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-4 shadow-xs">
                    <div class="border-b border-slate-200 dark:border-slate-700 pb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>🏢</span>
                            <span>Per-Branch AI Access Matrix</span>
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Control which branch portals can access Reorder Forecasts and the Ask-Your-Business Assistant.
                        </p>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 max-h-96 overflow-y-auto">
                        <div
                            v-for="b in branches"
                            :key="b.id"
                            class="py-3 flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ b.name }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    Slug: /{{ b.slug }}
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="toggleBranchAi(b)"
                                :class="[
                                    'px-3 py-1 rounded-full text-[11px] font-bold transition cursor-pointer flex-shrink-0',
                                    b.is_ai_enabled
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300'
                                ]"
                            >
                                {{ b.is_ai_enabled ? 'AI Enabled' : 'Disabled' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- AI Feature Capabilities Guide Card -->
                <div class="bg-gradient-to-br from-indigo-900/90 to-slate-900 text-white rounded-2xl p-5 border border-indigo-700/40 shadow-md space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300 flex items-center gap-1.5">
                        <span>💡</span>
                        <span>AI Module Capabilities</span>
                    </h3>
                    <ul class="text-xs space-y-2 text-indigo-100/90 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span><strong>Inventory Forecasting:</strong> Rolling 14-day sales velocity and packaging variant conversion.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span><strong>Ask-Your-Business:</strong> Grounded plain-language query interpreter with verifiable raw figures.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span><strong>Multi-Provider:</strong> Zero lock-in. Switch seamlessly between Gemini, OpenAI, DeepSeek, Kimi, and Claude.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
