<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { useCurrency } from '@/Composables/useCurrency'
import axios from 'axios'

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
    "How did sales this week compare to last week?",
    "What are our top 5 selling items this month?",
    "Is any inventory item running low or out of stock?",
    "Show total tax collected this month",
    "Which cashier had the highest sales volume?",
    "How many unread notifications or stock conflicts do we have?",
]

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })
}

onMounted(() => {
    scrollToBottom()
})

async function sendMessage(text) {
    const promptToSend = (text || inputPrompt.value).trim()
    if (!promptToSend || isSending.value) return

    inputPrompt.value = ''

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
        <div class="h-[calc(100vh-6.5rem)] flex flex-col bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
            <!-- ── Header Bar ────────────────────────────────────────────────── -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-850 flex items-center justify-between gap-4 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center text-lg font-bold shadow-sm">
                    💬
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-sm font-black text-slate-900 dark:text-white">
                            Ask-Your-Business AI Assistant
                        </h1>
                        <span v-if="branch" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                            {{ branch.name }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Ask plain-language questions grounded in verified live sales, inventory, tax, IMEI, and staff performance records.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Super Admin Branch Filter Selector -->
                <div v-if="isSuperAdmin && branches?.length > 0">
                    <select
                        v-model="selectedBranchId"
                        class="bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-600 outline-none"
                    >
                        <option value="">All Branches (Cross-Branch)</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Provider Badge -->
                <div v-if="activeProvider" class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>{{ activeProvider.name }} ({{ activeProvider.model }})</span>
                </div>

                <!-- Clear Session Button -->
                <button
                    type="button"
                    @click="clearChat"
                    class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-bold transition cursor-pointer"
                    title="Clear conversation"
                >
                    Clear History
                </button>
            </div>
        </div>

        <!-- ── Messages Container ────────────────────────────────────────── -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/50 dark:bg-slate-900/40"
        >
            <!-- Welcome banner if no messages -->
            <div v-if="messages.length === 0" class="max-w-2xl mx-auto text-center py-10 space-y-4">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-white flex items-center justify-center text-3xl shadow-lg shadow-emerald-900/20">
                    ✨
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
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
                        class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-left text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-emerald-500 dark:hover:border-emerald-400 hover:shadow-xs transition cursor-pointer flex items-center justify-between"
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
                    class="bg-emerald-600 text-white px-4 py-3 rounded-2xl rounded-tr-xs text-xs font-medium max-w-lg shadow-sm"
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
                    <!-- Header with Grounding Badge -->
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-2">
                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                            <span>✨</span>
                            <span>Skynet AI Insight</span>
                            <span v-if="msg.detected_intent" class="px-2 py-0.2 rounded-full bg-slate-100 dark:bg-slate-700 font-mono text-[9px]">
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

                    <!-- Conversational Response Text -->
                    <div class="leading-relaxed whitespace-pre-line text-slate-800 dark:text-slate-200">
                        {{ msg.message }}
                    </div>

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
        <div v-if="messages.length > 0" class="px-4 py-2 border-t border-slate-100 dark:border-slate-700/60 bg-white dark:bg-slate-800 flex items-center gap-1.5 overflow-x-auto scrollbar-none flex-shrink-0">
            <button
                v-for="chip in suggestedPrompts.slice(0, 4)"
                :key="chip"
                type="button"
                @click="sendMessage(chip)"
                class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[11px] font-medium whitespace-nowrap hover:bg-slate-200 dark:hover:bg-slate-600 transition cursor-pointer"
            >
                {{ chip }}
            </button>
        </div>

        <!-- ── Input Bar ─────────────────────────────────────────────────── -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                <input
                    v-model="inputPrompt"
                    type="text"
                    :disabled="isSending"
                    placeholder="Ask a question about sales, inventory velocity, staff, tax, or IMEIs..."
                    class="flex-1 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-3 text-xs rounded-xl border border-slate-300 dark:border-slate-600 focus:border-emerald-500 outline-none transition disabled:opacity-50"
                />

                <button
                    type="submit"
                    :disabled="!inputPrompt.trim() || isSending"
                    class="px-5 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-900/20 transition disabled:opacity-40 cursor-pointer flex items-center gap-1.5"
                >
                    <span>Send</span>
                    <span>→</span>
                </button>
            </form>
        </div>
        </div>
    </component>
</template>
