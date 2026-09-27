<script setup>
import { ref, computed, nextTick, onMounted, inject } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'

defineOptions({ layout: null })

const toggleMobileSidebar = inject('toggleMobileSidebar', null)
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

// ── Markdown Parser & Sanitizer Setup ────────────────────────────────────────
marked.use({
    gfm: true,
    breaks: true,
})

// Hook DOMPurify to guarantee external links open safely in a new tab
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank')
        node.setAttribute('rel', 'noopener noreferrer')
        node.setAttribute('class', 'text-emerald-600 dark:text-emerald-400 font-semibold underline hover:text-emerald-500 transition')
    }
})

function renderMarkdown(content) {
    if (!content || typeof content !== 'string') return ''
    try {
        const rawHtml = marked.parse(content)
        const sanitized = DOMPurify.sanitize(rawHtml, {
            ADD_ATTR: ['target', 'rel', 'class'],
            ADD_TAGS: ['table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'span', 'code', 'pre', 'blockquote', 'hr', 'a', 'p', 'strong', 'em', 'b', 'i', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'br'],
        })

        // Wrap any <table> in a responsive scrollable card container if needed
        return sanitized
            .replace(/<table/gi, '<div class="ai-table-wrapper overflow-x-auto my-3 rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs"><table class="ai-table w-full border-collapse text-left text-xs"')
            .replace(/<\/table>/gi, '</table></div>')
    } catch (e) {
        console.warn('Markdown parsing error:', e)
        return DOMPurify.sanitize(content)
    }
}

const page = usePage()
const isSuperAdmin = computed(() => !!page.props.auth?.user?.is_super_admin)

const props = defineProps({
    branch:         Object,
    branches:       Array,
    sessionId:      String,
    initialLogs:    Array,
    isAiConfigured: Boolean,
    activeProvider: Object,
})

const layoutComponent = computed(() => {
    if (isSuperAdmin.value && !props.branch) {
        return SuperAdminLayout
    }
    return PosLayout
})

const { format } = useCurrency()

const messages = ref(props.initialLogs ? [...props.initialLogs] : [])
const inputPrompt = ref('')
const isSending = ref(false)
const selectedBranchId = ref(props.branch?.id || '')
const messagesContainer = ref(null)

const suggestedPrompts = [
    "How many held sales are currently open?",
    "What were my expenses this month?",
    "What's my total inventory worth right now?",
    "Which customers owe the most right now?",
    "Which customers haven't paid in over 30 days?",
    "Who performed most among our cashiers and staff this month?",
    "Check if there is any suspicious activity, high discounts, or fraud risks",
    "Is our business growing and at what rate?",
    "Analyze our rate of sale, peak rush hours, and payment method distribution",
    "What are our top 8 selling items and profit margins?",
    "Show total tax collected this month",
]

const textareaRef = ref(null)

function adjustTextareaHeight() {
    nextTick(() => {
        const el = textareaRef.value
        if (!el) return
        el.style.height = 'auto'
        // Clamp height between 1 line (~40px) and max 4 lines (~108px)
        const clampedHeight = Math.min(Math.max(el.scrollHeight, 40), 108)
        el.style.height = `${clampedHeight}px`
    })
}

function resetTextareaHeight() {
    nextTick(() => {
        const el = textareaRef.value
        if (!el) return
        el.style.height = '40px'
    })
}

function handleEnterKey(e) {
    if (e.isComposing) return
    sendMessage()
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })
}

onMounted(() => {
    scrollToBottom()
    resetTextareaHeight()
})

async function sendMessage(text) {
    const promptToSend = (text || inputPrompt.value).trim()
    if (!promptToSend || isSending.value) return

    inputPrompt.value = ''
    resetTextareaHeight()

    // Add user message locally
    messages.value.push({
        id: Date.now(),
        role: 'user',
        message: promptToSend,
        created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    })

    scrollToBottom()
    isSending.value = true

    const branchSlug = props.branch?.slug || route().params?.branch
    const askRoute = (isSuperAdmin.value && !props.branch)
        ? route('superadmin.ai.chat.ask')
        : route('pos.ai.chat.ask', { branch: branchSlug })

    try {
        const res = await axios.post(askRoute, {
            question:   promptToSend,
            session_id: props.sessionId,
            branch_id:  selectedBranchId.value || undefined,
        })

        messages.value.push({
            id:                 Date.now() + 1,
            role:               'assistant',
            message:            res.data.ai_response,
            detected_intent:    res.data.intent,
            structured_payload: res.data.structured_data,
            provider_used:      res.data.provider_used,
            model_used:         res.data.model_used,
            is_ai_generated:    res.data.is_ai_generated,
            execution_time_ms:  res.data.execution_time_ms,
            created_at:         new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        })
    } catch (err) {
        messages.value.push({
            id: Date.now() + 1,
            role: 'assistant',
            message: "Sorry, I encountered an error processing your query: " + (err.response?.data?.message || err.message),
            is_error: true,
            created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        })
    } finally {
        isSending.value = false
        scrollToBottom()
    }
}

function isAiResponse(msg) {
    if (msg.is_ai_generated !== undefined) {
        return Boolean(msg.is_ai_generated)
    }
    return msg.provider_used && !['template_fallback', 'local_database_summary', 'local_rules', null, ''].includes(msg.provider_used)
}

function formatProviderName(providerId) {
    const map = {
        gemini:   'Google Gemini',
        openai:   'OpenAI',
        deepseek: 'DeepSeek',
        kimi:     'Moonshot Kimi',
        claude:   'Anthropic Claude',
    }
    return map[providerId] || providerId
}

function clearChat() {
    const branchSlug = props.branch?.slug || route().params?.branch
    const clearRoute = (isSuperAdmin.value && !props.branch)
        ? route('superadmin.ai.chat.clear')
        : route('pos.ai.chat.clear', { branch: branchSlug })

    router.delete(clearRoute, {
        data: { session_id: props.sessionId },
        preserveScroll: true,
        onSuccess: () => {
            messages.value = []
        },
    })
}

function copyMessage(text) {
    navigator.clipboard?.writeText(text)
}
</script>

<template>
    <component :is="layoutComponent">
        <div class="w-full h-full flex-1 flex flex-col items-center justify-center p-3 sm:p-5 lg:p-6 min-h-0 overflow-hidden bg-slate-100/70 dark:bg-slate-950/70">
            <div class="w-full max-w-4xl xl:max-w-5xl h-full flex flex-col bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xl overflow-hidden transition-all">
                <!-- ── Header Bar ────────────────────────────────────────────────── -->
                <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-850/80 backdrop-blur-xs flex items-center justify-between gap-3 sm:gap-4 flex-shrink-0">
                    <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                        <!-- Mobile Hamburger Button -->
                        <button
                            v-if="toggleMobileSidebar"
                            type="button"
                            @click="toggleMobileSidebar"
                            class="lg:hidden p-1.5 rounded-xl text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 transition flex-shrink-0 cursor-pointer"
                            title="Toggle menu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>

                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center text-lg font-bold shadow-sm flex-shrink-0">
                            💬
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h1 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white truncate">
                                    Ask-Your-Business AI Assistant
                                </h1>
                                <span v-if="branch" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 flex-shrink-0">
                                    {{ branch.name }}
                                </span>
                            </div>
                            <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate hidden sm:block">
                                Grounded in verified live sales, inventory, debt, IMEI & staff performance
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <!-- Super Admin Branch Filter Selector -->
                        <div v-if="isSuperAdmin && branches?.length > 0">
                            <select
                                v-model="selectedBranchId"
                                class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 outline-none font-medium shadow-2xs cursor-pointer"
                            >
                                <option value="">🏢 All Branches</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Provider Badge -->
                        <div v-if="activeProvider" class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>{{ activeProvider.name }} ({{ activeProvider.model }})</span>
                        </div>

                        <ThemeToggle class="flex-shrink-0" />

                        <!-- Clear Session Button -->
                        <button
                            type="button"
                            @click="clearChat"
                            class="px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold transition cursor-pointer border border-slate-200 dark:border-slate-700/80"
                            title="Clear conversation"
                        >
                            Clear History
                        </button>
                    </div>
                </div>

                <!-- ── Messages Container ────────────────────────────────────────── -->
                <div
                    ref="messagesContainer"
                    class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 sm:space-y-6 bg-slate-50/40 dark:bg-slate-900/40"
                >
                    <!-- Welcome banner if no messages -->
                    <div v-if="messages.length === 0" class="max-w-2xl mx-auto text-center py-8 sm:py-10 space-y-4">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-white flex items-center justify-center text-2xl sm:text-3xl shadow-lg shadow-emerald-900/20">
                            ✨
                        </div>
                        <div>
                            <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                Welcome to Ask-Your-Business AI
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-1">
                                Type any business question below to generate grounded insights directly from your point-of-sale database.
                            </p>
                        </div>

                        <!-- Suggestion Chips Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-xl mx-auto pt-2">
                            <button
                                v-for="prompt in suggestedPrompts"
                                :key="prompt"
                                type="button"
                                @click="sendMessage(prompt)"
                                class="p-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-left text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-emerald-500 dark:hover:border-emerald-400 hover:shadow-xs transition cursor-pointer flex items-center justify-between"
                            >
                                <span>{{ prompt }}</span>
                                <span class="text-emerald-500 font-bold ml-2">→</span>
                            </button>
                        </div>
                    </div>

                    <!-- Message List -->
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex flex-col space-y-2 max-w-3xl"
                        :class="msg.role === 'user' ? 'ml-auto items-end' : 'mr-auto items-start'"
                    >
                        <!-- User Message -->
                        <div
                            v-if="msg.role === 'user'"
                            class="bg-emerald-600 text-white px-4 py-3 rounded-2xl rounded-tr-xs text-xs font-medium max-w-lg shadow-sm whitespace-pre-wrap leading-relaxed"
                        >
                            {{ msg.message }}
                            <div class="text-[9px] text-emerald-200 text-right mt-1 font-mono">
                                {{ msg.created_at }}
                            </div>
                        </div>

                        <!-- Assistant Message Box -->
                        <div
                            v-else
                            class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl rounded-tl-xs p-4 text-xs space-y-3 shadow-xs w-full max-w-2xl text-slate-800 dark:text-slate-200"
                        >
                            <!-- Header with AI vs Non-AI Status Badge -->
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/80 pb-2.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <!-- AI Generated Badge -->
                                    <span
                                        v-if="isAiResponse(msg)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 text-[10px] font-bold"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>✨ AI Generated</span>
                                        <span v-if="msg.provider_used" class="opacity-80 font-normal">({{ formatProviderName(msg.provider_used) }}{{ msg.model_used ? ' · ' + msg.model_used : '' }})</span>
                                    </span>

                                    <!-- NON-AI Generated Badge -->
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-500/30 text-[10px] font-bold"
                                    >
                                        <span>📊</span>
                                        <span>Database Summary (Non-AI Generated)</span>
                                    </span>

                                    <span v-if="msg.detected_intent" class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 font-mono text-[9px] text-slate-600 dark:text-slate-300">
                                        {{ msg.detected_intent }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span v-if="msg.execution_time_ms" class="text-[9px] text-slate-400 font-mono">
                                        {{ msg.execution_time_ms }}ms
                                    </span>
                                    <button
                                        type="button"
                                        @click="copyMessage(msg.message)"
                                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-[10px] cursor-pointer"
                                        title="Copy answer"
                                    >
                                        📋 Copy
                                    </button>
                                </div>
                            </div>

                            <!-- Non-AI Context Banner -->
                            <div
                                v-if="!isAiResponse(msg)"
                                class="p-2.5 rounded-xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 flex items-start gap-2"
                            >
                                <span class="text-sm flex-shrink-0">⚠️</span>
                                <div class="space-y-0.5 text-[11px]">
                                    <p class="font-bold text-amber-950 dark:text-amber-200">Non-AI Generated Response</p>
                                    <p class="text-[10px] text-slate-600 dark:text-slate-300 leading-relaxed">
                                        This summary was compiled directly from database records without an active AI model. To enable in-depth natural language reasoning, strategic takeaways, and multi-factor forecasting, configure an AI API key in 
                                        <Link v-if="isSuperAdmin" :href="route('superadmin.ai.settings')" class="font-bold text-amber-700 dark:text-amber-300 underline hover:text-amber-800">AI Settings</Link>
                                        <span v-else class="font-bold">AI Settings</span>.
                                    </p>
                                </div>
                            </div>

                            <!-- ── Structured KPI Metrics Data Cards (if present) ── -->
                            <div v-if="msg.structured_payload" class="space-y-2.5">
                                <!-- Sales Performance Cards -->
                                <div v-if="msg.structured_payload.today && msg.structured_payload.this_week" class="grid grid-cols-3 gap-2">
                                    <div class="bg-slate-50 dark:bg-slate-750 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-700">
                                        <div class="text-[10px] text-slate-400 font-bold uppercase">Today</div>
                                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                                            {{ format(msg.structured_payload.today.revenue) }}
                                        </div>
                                        <div class="text-[9px] text-slate-500">{{ msg.structured_payload.today.orders }} orders</div>
                                    </div>

                                    <div class="bg-slate-50 dark:bg-slate-750 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-700">
                                        <div class="text-[10px] text-slate-400 font-bold uppercase">This Week</div>
                                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                                            {{ format(msg.structured_payload.this_week.revenue) }}
                                        </div>
                                        <div class="text-[9px] text-slate-500">{{ msg.structured_payload.this_week.orders }} orders</div>
                                    </div>

                                    <div class="bg-slate-50 dark:bg-slate-750 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-700">
                                        <div class="text-[10px] text-slate-400 font-bold uppercase">This Month</div>
                                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                                            {{ format(msg.structured_payload.this_month.revenue) }}
                                        </div>
                                        <div class="text-[9px] text-slate-500">{{ msg.structured_payload.this_month.orders }} orders</div>
                                    </div>
                                </div>

                                <!-- Top Items Mini-Table -->
                                <div v-if="msg.structured_payload.top_items && msg.structured_payload.top_items.length > 0" class="bg-slate-50 dark:bg-slate-750 rounded-xl p-2.5 border border-slate-200/60 dark:border-slate-700">
                                    <div class="text-[10px] font-bold uppercase text-slate-400 mb-1">Top Selling Items</div>
                                    <div class="space-y-1 divide-y divide-slate-100 dark:divide-slate-700/60">
                                        <div v-for="item in msg.structured_payload.top_items" :key="item.item_name" class="pt-1 flex items-center justify-between text-xs">
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.item_name }}</span>
                                            <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ item.units_sold }} pcs • {{ format(item.revenue) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Low Stock Items List -->
                                <div v-if="msg.structured_payload.items_at_risk && msg.structured_payload.items_at_risk.length > 0" class="bg-slate-50 dark:bg-slate-750 rounded-xl p-2.5 border border-slate-200/60 dark:border-slate-700">
                                    <div class="text-[10px] font-bold uppercase text-slate-400 mb-1">Low Stock Products</div>
                                    <div class="space-y-1 divide-y divide-slate-100 dark:divide-slate-700/60">
                                        <div v-for="item in msg.structured_payload.items_at_risk" :key="item.item_name" class="pt-1 flex items-center justify-between text-xs">
                                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.item_name }} ({{ item.category }})</span>
                                            <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-950 text-rose-600 font-mono">
                                                {{ item.stock }} left
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Conversational Response Text (Rendered Markdown) -->
                            <div
                                class="ai-markdown-content text-xs leading-relaxed text-slate-800 dark:text-slate-200"
                                v-html="renderMarkdown(msg.message)"
                            ></div>

                            <div class="text-[9px] text-slate-400 text-right font-mono pt-1">
                                {{ msg.created_at }}
                            </div>
                        </div>
                    </div>

                    <!-- Typing indicator -->
                    <div v-if="isSending" class="flex items-center gap-2 text-xs text-slate-400 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-4 py-2.5 rounded-2xl w-fit">
                        <span class="animate-spin text-sm">✨</span>
                        <span>Querying verified database records & synthesizing answer...</span>
                    </div>
                </div>

                <!-- ── Suggestion Chips Bar ──────────────────────────────────────── -->
                <div v-if="messages.length > 0" class="px-4 py-2 border-t border-slate-100 dark:border-slate-700/60 bg-white dark:bg-slate-850 flex items-center gap-1.5 overflow-x-auto scrollbar-none flex-shrink-0">
                    <button
                        v-for="chip in suggestedPrompts.slice(0, 4)"
                        :key="chip"
                        type="button"
                        @click="sendMessage(chip)"
                        class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[11px] font-medium whitespace-nowrap hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer border border-slate-200/50 dark:border-slate-700/50"
                    >
                        {{ chip }}
                    </button>
                </div>

                <!-- ── Input Bar (Multi-line up to 4 lines) ───────────────────────── -->
                <div class="p-3 sm:p-4 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex-shrink-0">
                    <form @submit.prevent="sendMessage()" class="flex items-end gap-2 sm:gap-3">
                        <div class="relative flex-1">
                            <textarea
                                ref="textareaRef"
                                v-model="inputPrompt"
                                rows="1"
                                :disabled="isSending"
                                @input="adjustTextareaHeight"
                                @keydown.enter.exact.prevent="handleEnterKey"
                                placeholder="Ask a question about sales, inventory velocity, staff, tax, or IMEIs... (Press Enter to send, Shift+Enter for new line)"
                                class="w-full bg-slate-50 dark:bg-slate-800/90 text-slate-900 dark:text-white px-4 py-2.5 text-xs sm:text-sm rounded-2xl border border-slate-300 dark:border-slate-700 focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-1 focus:ring-emerald-500 outline-none transition-all resize-none max-h-[108px] overflow-y-auto leading-relaxed disabled:opacity-50 shadow-inner"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            :disabled="!inputPrompt.trim() || isSending"
                            class="px-4 sm:px-5 py-2.5 sm:py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-bold rounded-2xl shadow-md shadow-emerald-900/20 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-1.5 flex-shrink-0 min-h-[42px]"
                        >
                            <span v-if="!isSending" class="flex items-center gap-1.5">
                                <span>Send</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </span>
                            <span v-else class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                <span>Thinking...</span>
                            </span>
                        </button>
                    </form>
                    <div class="flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500 px-1 pt-1.5">
                        <span>Shift + Enter for new line • Enter to send</span>
                        <span v-if="inputPrompt.length > 0">{{ inputPrompt.length }} chars</span>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
:deep(.ai-markdown-content) {
    word-break: break-word;
}

:deep(.ai-markdown-content p) {
    margin-bottom: 0.6rem;
    line-height: 1.6;
}
:deep(.ai-markdown-content p:last-child) {
    margin-bottom: 0;
}

:deep(.ai-markdown-content h1) {
    font-size: 0.95rem;
    font-weight: 800;
    margin-top: 0.85rem;
    margin-bottom: 0.4rem;
    color: #0f172a;
}
.dark :deep(.ai-markdown-content h1) {
    color: #f8fafc;
}

:deep(.ai-markdown-content h2) {
    font-size: 0.85rem;
    font-weight: 700;
    margin-top: 0.75rem;
    margin-bottom: 0.35rem;
    color: #059669;
}
.dark :deep(.ai-markdown-content h2) {
    color: #34d399;
}

:deep(.ai-markdown-content h3),
:deep(.ai-markdown-content h4) {
    font-size: 0.8rem;
    font-weight: 700;
    margin-top: 0.6rem;
    margin-bottom: 0.3rem;
    color: #0f172a;
}
.dark :deep(.ai-markdown-content h3),
.dark :deep(.ai-markdown-content h4) {
    color: #f8fafc;
}

:deep(.ai-markdown-content strong),
:deep(.ai-markdown-content b) {
    font-weight: 700;
    color: #0f172a;
}
.dark :deep(.ai-markdown-content strong),
.dark :deep(.ai-markdown-content b) {
    color: #ffffff;
}

:deep(.ai-markdown-content em),
:deep(.ai-markdown-content i) {
    font-style: italic;
}

:deep(.ai-markdown-content ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-top: 0.35rem;
    margin-bottom: 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
:deep(.ai-markdown-content ol) {
    list-style-type: decimal;
    padding-left: 1.25rem;
    margin-top: 0.35rem;
    margin-bottom: 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
:deep(.ai-markdown-content li) {
    line-height: 1.5;
}

:deep(.ai-table-wrapper) {
    margin: 0.65rem 0;
    background-color: transparent;
}
:deep(.ai-table) {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
}
:deep(.ai-table th) {
    background-color: #f1f5f9;
    padding: 0.5rem 0.75rem;
    font-weight: 700;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    white-space: nowrap;
}
.dark :deep(.ai-table th) {
    background-color: #334155;
    border-bottom: 1px solid #475569;
    color: #e2e8f0;
}
:deep(.ai-table td) {
    padding: 0.45rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.dark :deep(.ai-table td) {
    border-bottom: 1px solid #334155;
}
:deep(.ai-table tr:last-child td) {
    border-bottom: none;
}
:deep(.ai-table tr:hover td) {
    background-color: #f8fafc;
}
.dark :deep(.ai-table tr:hover td) {
    background-color: rgba(51, 65, 85, 0.4);
}

:deep(.ai-markdown-content code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem;
    padding: 0.15rem 0.35rem;
    border-radius: 0.375rem;
    background-color: #f1f5f9;
    color: #059669;
    border: 1px solid #e2e8f0;
}
.dark :deep(.ai-markdown-content code) {
    background-color: #334155;
    color: #34d399;
    border: 1px solid #475569;
}

:deep(.ai-markdown-content pre) {
    margin: 0.5rem 0;
    padding: 0.75rem;
    border-radius: 0.75rem;
    background-color: #0f172a;
    color: #f8fafc;
    overflow-x: auto;
    border: 1px solid #1e293b;
}
:deep(.ai-markdown-content pre code) {
    padding: 0;
    background-color: transparent;
    color: inherit;
    border: none;
}

:deep(.ai-markdown-content blockquote) {
    border-left: 3px solid #10b981;
    padding-left: 0.75rem;
    margin: 0.5rem 0;
    font-style: italic;
    color: #64748b;
}
.dark :deep(.ai-markdown-content blockquote) {
    color: #94a3b8;
}

:deep(.ai-markdown-content hr) {
    margin: 0.75rem 0;
    border: none;
    border-top: 1px solid #e2e8f0;
}
.dark :deep(.ai-markdown-content hr) {
    border-top: 1px solid #475569;
}
</style>
