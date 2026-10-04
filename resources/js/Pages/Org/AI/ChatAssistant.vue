<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import OrgLayout from '@/Layouts/OrgLayout.vue'
import axios from 'axios'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

defineOptions({ layout: OrgLayout })

const props = defineProps({
    organization:   Object,
    branches:       Array,
    selectedBranch: Object,
    sessionId:      String,
    initialLogs:    Array,
    creditSummary:  Object,
    activeProvider: Object,
})

const creditSummary = ref(props.creditSummary || null)

marked.use({
    gfm: true,
    breaks: true,
})

DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank')
        node.setAttribute('rel', 'noopener noreferrer')
        node.setAttribute('class', 'text-purple-600 dark:text-purple-400 font-semibold underline hover:text-purple-500 transition')
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

        return sanitized
            .replace(/<table/gi, '<div class="ai-table-wrapper overflow-x-auto my-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs"><table class="ai-table w-full border-collapse text-left text-xs"')
            .replace(/<\/table>/gi, '</table></div>')
    } catch (e) {
        console.warn('Markdown parsing error:', e)
        return DOMPurify.sanitize(content)
    }
}

const messages = ref(props.initialLogs ? [...props.initialLogs] : [])
const inputPrompt = ref('')
const isSending = ref(false)
const selectedBranchId = ref(props.selectedBranch?.id || '')
const messagesContainer = ref(null)

const suggestedPrompts = [
    "Why am I on loss and which item caused that?",
    "Check if high buy costs or discounts made me lose profit",
    "What were total sales today across our branches?",
    "What's our total inventory worth right now?",
    "Which customers owe the most right now?",
    "Which customers haven't paid in over 30 days?",
    "What were our operating expenses this month?",
    "How many held sales are currently open?",
    "What are our top 10 selling products?",
    "Check if there are any suspicious discounts or cashier risks",
    "Show receipt RC2026...",
]

const textareaRef = ref(null)

function adjustTextareaHeight() {
    nextTick(() => {
        const el = textareaRef.value
        if (!el) return
        el.style.height = 'auto'
        const clampedHeight = Math.min(Math.max(el.scrollHeight, 40), 108)
        el.style.height = `${clampedHeight}px`
    })
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })
}

const scrollToNewMessage = (domId) => {
    nextTick(() => {
        setTimeout(() => {
            const container = messagesContainer.value
            if (!container) return
            const targetEl = document.getElementById(domId)
            if (!targetEl) {
                container.scrollTop = container.scrollHeight
                return
            }

            const containerRect = container.getBoundingClientRect()
            const targetRect = targetEl.getBoundingClientRect()
            const scrollTarget = container.scrollTop + (targetRect.top - containerRect.top) - 16

            container.scrollTo({
                top: Math.max(0, scrollTarget),
                behavior: 'smooth',
            })
        }, 80)
    })
}

onMounted(() => {
    scrollToBottom()
})

const sendPrompt = async (promptText) => {
    const text = (promptText || inputPrompt.value).trim()
    if (!text || isSending.value) return

    inputPrompt.value = ''
    adjustTextareaHeight()

    // Add user message to UI
    messages.value.push({
        id: Date.now(),
        role: 'user',
        message: text,
        created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    })
    scrollToBottom()

    isSending.value = true
    let assistantMsgId = null

    try {
        const response = await axios.post(route('org.ai.chat.ask'), {
            question:   text,
            session_id: props.sessionId,
            branch_id:  selectedBranchId.value || undefined,
        })

        const resData = response.data
        if (resData.credit_summary) {
            creditSummary.value = resData.credit_summary
        }

        assistantMsgId = Date.now() + 1

        if (resData.intent === 'credits_exhausted') {
            messages.value.push({
                id:                 assistantMsgId,
                role:               'assistant',
                message:            resData.response,
                detected_intent:    'credits_exhausted',
                structured_payload: resData.structured_data,
                created_at:         new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            })
            return
        }

        messages.value.push({
            id:                 assistantMsgId,
            role:               'assistant',
            message:            resData.ai_response || 'No response generated.',
            detected_intent:    resData.intent,
            structured_payload: resData.structured_data,
            provider_used:      resData.provider_used,
            model_used:         resData.model_used,
            is_ai_generated:    resData.is_ai_generated,
            execution_time_ms:  resData.execution_time_ms,
            created_at:         new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        })
    } catch (err) {
        assistantMsgId = Date.now() + 1
        messages.value.push({
            id:         assistantMsgId,
            role:       'assistant',
            message:    '⚠️ An error occurred while retrieving business metrics. Please verify your query and try again.',
            created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        })
    } finally {
        isSending.value = false
        if (assistantMsgId) {
            scrollToNewMessage('org-msg-' + assistantMsgId)
        } else {
            scrollToBottom()
        }
    }
}

const clearConversation = async () => {
    if (!confirm('Clear your current conversation history?')) return
    try {
        await axios.delete(route('org.ai.chat.clear'), {
            params: { session_id: props.sessionId }
        })
        messages.value = []
    } catch (e) {
        console.error('Failed to clear conversation', e)
    }
}
</script>

<template>
    <Head :title="`AI Copilot - ${organization.name}`" />

    <div class="max-w-5xl mx-auto flex flex-col h-[calc(100vh-8rem)]">
        <!-- ── Chat Top Header ─────────────────────────────────────────── -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs flex items-center justify-between gap-4 flex-shrink-0 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-600/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg">
                    ✨
                </div>
                <div>
                    <h1 class="text-sm font-bold text-slate-900 dark:text-white leading-tight">
                        AI Executive Copilot
                    </h1>
                    <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                        <span>Organization Scope:</span>
                        <strong class="text-slate-700 dark:text-slate-300">{{ organization.name }}</strong>
                    </div>
                </div>
            </div>

            <!-- Scope selector & clear -->
            <div class="flex items-center gap-2">
                <!-- AI Credit Meter Badge -->
                <div v-if="creditSummary" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors shadow-2xs"
                    :class="creditSummary.is_exhausted
                        ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30'
                        : 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/25'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="creditSummary.is_exhausted ? 'bg-rose-500 animate-pulse' : 'bg-purple-500'"></span>
                    <span v-if="creditSummary.is_unmetered">Unmetered AI</span>
                    <span v-else>
                        {{ creditSummary.remaining }} / {{ creditSummary.monthly_quota }} Credits
                    </span>
                </div>

                <select
                    v-model="selectedBranchId"
                    class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500 cursor-pointer"
                >
                    <option value="">All Organization Branches</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option>
                </select>

                <button
                    @click="clearConversation"
                    type="button"
                    class="px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer"
                    title="Clear history"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- ── Messages Canvas ──────────────────────────────────────────── -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-6"
        >
            <!-- Welcome empty state -->
            <div v-if="!messages.length" class="text-center py-10 space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-purple-600/10 text-purple-600 dark:text-purple-400 mx-auto flex items-center justify-center text-2xl">
                    🤖
                </div>
                <div class="space-y-1">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Ask anything about your business</h2>
                    <p class="text-xs text-slate-400 max-w-md mx-auto">
                        Query sales, inventory value, debtor balances, operating costs, or look up specific receipt numbers across {{ organization.name }} branches.
                    </p>
                </div>

                <!-- Suggested Quick Chips -->
                <div class="flex flex-wrap justify-center gap-2 max-w-2xl mx-auto pt-2">
                    <button
                        v-for="chip in suggestedPrompts"
                        :key="chip"
                        @click="sendPrompt(chip)"
                        type="button"
                        class="px-3 py-1.5 bg-slate-50 hover:bg-purple-50 dark:bg-slate-800/80 dark:hover:bg-purple-950/40 text-slate-700 dark:text-slate-300 hover:text-purple-700 dark:hover:text-purple-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs transition cursor-pointer text-left"
                    >
                        {{ chip }}
                    </button>
                </div>
            </div>

            <!-- Messages Stream -->
            <div v-for="(msg, idx) in messages" :key="msg.id || idx" :id="'org-msg-' + (msg.id || idx)" class="space-y-1">
                <!-- User Message -->
                <div v-if="msg.role === 'user'" class="flex justify-end">
                    <div class="max-w-xl bg-purple-600 text-white rounded-2xl rounded-tr-xs px-4 py-3 text-xs sm:text-sm font-medium shadow-sm">
                        <p class="whitespace-pre-wrap">{{ msg.message }}</p>
                        <div class="text-[10px] text-purple-200 text-right mt-1">{{ msg.created_at }}</div>
                    </div>
                </div>

                <!-- Assistant Message -->
                <div v-else class="flex justify-start">
                    <div class="max-w-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 rounded-2xl rounded-tl-xs p-5 text-xs sm:text-sm shadow-xs space-y-2">
                        <div
                            class="prose prose-sm dark:prose-invert max-w-none text-xs sm:text-sm"
                            v-html="renderMarkdown(msg.message)"
                        ></div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-800/60 text-[10px] text-slate-400">
                            <span v-if="msg.execution_time_ms">{{ msg.execution_time_ms }}ms</span>
                            <span>{{ msg.created_at }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading indicator -->
            <div v-if="isSending" class="flex justify-start">
                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl rounded-tl-xs p-4 flex items-center gap-2 text-xs text-purple-600 dark:text-purple-400">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Analyzing organization data...</span>
                </div>
            </div>
        </div>

        <!-- Credit Exhaustion Banner -->
        <div v-if="creditSummary?.is_exhausted" class="mt-4 p-3 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-200 text-xs flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-base">⚠️</span>
                <span><strong>Monthly AI credit limit reached</strong> ({{ creditSummary.used }}/{{ creditSummary.monthly_quota }} used). Credits will reset on {{ creditSummary.resets_at }}.</span>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/20 text-amber-800 dark:text-amber-200 flex-shrink-0">
                {{ creditSummary.plan_name }} Plan
            </span>
        </div>

        <!-- ── Input Bar ────────────────────────────────────────────────── -->
        <div class="mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-2.5 shadow-xs flex items-center gap-2">
            <textarea
                ref="textareaRef"
                v-model="inputPrompt"
                :disabled="isSending || creditSummary?.is_exhausted"
                @input="adjustTextareaHeight"
                @keydown.enter.exact.prevent="sendPrompt()"
                rows="1"
                :placeholder="creditSummary?.is_exhausted
                    ? 'Monthly AI credit limit reached. Credits reset on ' + creditSummary.resets_at + ' or contact administrator to upgrade.'
                    : 'Ask about sales, stock, expenses, or type a receipt code...'"
                class="flex-1 bg-transparent px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 outline-none resize-none disabled:opacity-50"
            ></textarea>
            <button
                @click="sendPrompt()"
                :disabled="isSending || !inputPrompt.trim() || creditSummary?.is_exhausted"
                class="p-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-40 text-white rounded-xl transition shadow-xs cursor-pointer flex-shrink-0"
                title="Send"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </div>
</template>
