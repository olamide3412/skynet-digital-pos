<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

defineOptions({ layout: SuperAdminLayout })

marked.use({
    gfm: true,
    breaks: true,
})

function renderMarkdown(content) {
    if (!content || typeof content !== 'string') return ''
    try {
        const rawHtml = marked.parse(content)
        return DOMPurify.sanitize(rawHtml, {
            ADD_ATTR: ['target', 'rel', 'class'],
            ADD_TAGS: ['table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'span', 'code', 'pre', 'blockquote', 'hr', 'a', 'p', 'strong', 'em', 'b', 'i', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'br'],
        })
    } catch (e) {
        return content
    }
}

const props = defineProps({
    plans: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    includedItems: {
        type: Array,
        default: () => [],
    },
    valueProps: {
        type: Array,
        default: () => [],
    },
})

const activeTab = ref('plans') // 'plans' | 'shared' | 'included' | 'valueprops'

// ── Global Toggle ─────────────────────────────────────────────────────────────
const isTogglingVisibility = ref(false)
function toggleGlobalVisibility() {
    isTogglingVisibility.value = true
    router.post(route('superadmin.pricing-plans.toggle-section'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isTogglingVisibility.value = false
        },
    })
}

// ── Shared Content Settings Form ──────────────────────────────────────────────
const settingsForm = useForm({
    show_pricing_section: props.settings?.show_pricing_section ?? true,
    currency_symbol: props.settings?.currency_symbol || '₦',
    pricing_intro_heading: props.settings?.pricing_intro_heading || '',
    pricing_intro_text: props.settings?.pricing_intro_text || '',
    whats_included_heading: props.settings?.whats_included_heading || '',
    addons_heading: props.settings?.addons_heading || '',
    addons_content: props.settings?.addons_content || '',
    commercial_notes_heading: props.settings?.commercial_notes_heading || '',
    commercial_notes_content: props.settings?.commercial_notes_content || '',
})

function saveSettings() {
    settingsForm.put(route('superadmin.pricing-plans.settings.update'), {
        preserveScroll: true,
    })
}

// ── Plan Modal & Form State ───────────────────────────────────────────────────
const showPlanModal = ref(false)
const isEditingPlan = ref(false)
const currentPlanId = ref(null)

const planForm = useForm({
    name: '',
    tagline: '',
    first_year_price: '',
    renewal_price: '',
    branches_limit: '',
    users_limit: '',
    ai_credits: '',
    support_level: '',
    is_highlighted: false,
    sort_order: 1,
    is_active: true,
    features: [''],
})

function openCreatePlanModal() {
    isEditingPlan.value = false
    currentPlanId.value = null
    planForm.reset()
    planForm.clearErrors()
    planForm.sort_order = (props.plans?.length || 0) + 1
    planForm.is_active = true
    planForm.is_highlighted = false
    planForm.features = ['']
    showPlanModal.value = true
}

function openEditPlanModal(plan) {
    isEditingPlan.value = true
    currentPlanId.value = plan.id
    planForm.clearErrors()
    planForm.name = plan.name
    planForm.tagline = plan.tagline || ''
    planForm.first_year_price = plan.first_year_price
    planForm.renewal_price = plan.renewal_price
    planForm.branches_limit = plan.branches_limit
    planForm.users_limit = plan.users_limit
    planForm.ai_credits = plan.ai_credits || ''
    planForm.support_level = plan.support_level || ''
    planForm.is_highlighted = !!plan.is_highlighted
    planForm.sort_order = plan.sort_order
    planForm.is_active = !!plan.is_active
    planForm.features = plan.features?.length
        ? plan.features.map(f => f.feature_text)
        : ['']
    showPlanModal.value = true
}

function addFeatureInput() {
    planForm.features.push('')
}

function removeFeatureInput(index) {
    if (planForm.features.length > 1) {
        planForm.features.splice(index, 1)
    } else {
        planForm.features[0] = ''
    }
}

function submitPlanForm() {
    if (isEditingPlan.value) {
        planForm.put(route('superadmin.pricing-plans.plans.update', currentPlanId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showPlanModal.value = false
            },
        })
    } else {
        planForm.post(route('superadmin.pricing-plans.plans.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showPlanModal.value = false
            },
        })
    }
}

function deletePlan(plan) {
    if (confirm(`Are you sure you want to delete the plan "${plan.name}"? This action cannot be undone.`)) {
        router.delete(route('superadmin.pricing-plans.plans.destroy', plan.id), {
            preserveScroll: true,
        })
    }
}

function togglePlanActive(plan) {
    router.post(route('superadmin.pricing-plans.plans.toggle', plan.id), {}, {
        preserveScroll: true,
    })
}

function togglePlanHighlight(plan) {
    router.post(route('superadmin.pricing-plans.plans.highlight', plan.id), {}, {
        preserveScroll: true,
    })
}

function movePlanOrder(index, direction) {
    const list = [...props.plans]
    const targetIndex = index + direction
    if (targetIndex < 0 || targetIndex >= list.length) return

    const temp = list[index]
    list[index] = list[targetIndex]
    list[targetIndex] = temp

    const order = list.map(p => p.id)
    router.post(route('superadmin.pricing-plans.plans.reorder'), { order }, {
        preserveScroll: true,
    })
}

// ── What's Included Form ──────────────────────────────────────────────────────
const includedForm = useForm({
    items: props.includedItems?.length
        ? props.includedItems.map(i => i.item_text)
        : [''],
})

function addIncludedItem() {
    includedForm.items.push('')
}

function removeIncludedItem(idx) {
    if (includedForm.items.length > 1) {
        includedForm.items.splice(idx, 1)
    } else {
        includedForm.items[0] = ''
    }
}

function saveIncludedItems() {
    includedForm.put(route('superadmin.pricing-plans.included-items.update'), {
        preserveScroll: true,
    })
}

// ── Marketing Value Props Form ────────────────────────────────────────────────
const valuePropsForm = useForm({
    props: props.valueProps?.length
        ? props.valueProps.map(vp => ({ title: vp.title, subtext: vp.subtext }))
        : [{ title: '', subtext: '' }],
})

function addValueProp() {
    valuePropsForm.props.push({ title: '', subtext: '' })
}

function removeValueProp(idx) {
    if (valuePropsForm.props.length > 1) {
        valuePropsForm.props.splice(idx, 1)
    } else {
        valuePropsForm.props[0] = { title: '', subtext: '' }
    }
}

function saveValueProps() {
    valuePropsForm.put(route('superadmin.pricing-plans.value-props.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Pricing Plans &amp; Homepage Marketing" />

    <div class="space-y-6 max-w-7xl mx-auto pb-16">
        <!-- ══ TOP HEADER & VISIBILITY TOGGLE ═════════════════════════════ -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1.5">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-100 dark:bg-violet-950/70 border border-violet-300 dark:border-violet-700/60 text-violet-800 dark:text-violet-300 text-xs font-bold uppercase tracking-wider">
                    Marketing Display Engine
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Pricing Plans &amp; Homepage Showcase
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Configure public plan tiers, price tags, operational limits, value propositions, and commercial notes displayed on your marketing homepage.
                </p>
            </div>

            <!-- Global Feature Toggle Switch -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex items-center justify-between sm:justify-start gap-4 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs">
                    <div>
                        <div class="text-xs font-bold text-slate-900 dark:text-white">
                            Show on Homepage
                        </div>
                        <div class="text-[11px] text-slate-500">
                            {{ settings.show_pricing_section ? 'Live & visible on homepage' : 'Completely hidden from visitors' }}
                        </div>
                    </div>
                    <button
                        @click="toggleGlobalVisibility"
                        :disabled="isTogglingVisibility"
                        type="button"
                        :class="[
                            'relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-600 focus:ring-offset-2',
                            settings.show_pricing_section ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-slate-700'
                        ]"
                    >
                        <span
                            aria-hidden="true"
                            :class="[
                                'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                                settings.show_pricing_section ? 'translate-x-5' : 'translate-x-0'
                            ]"
                        />
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('superadmin.plan-capabilities.index')"
                        class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition shadow-xs"
                    >
                        <span>⚙️ Plan Capabilities (Engine Rules)</span>
                    </Link>

                    <a
                        href="/#pricing"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition shadow-xs cursor-pointer"
                    >
                        <span>View Live Section</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- ══ NAVIGATION TABS ════════════════════════════════════════════ -->
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 overflow-x-auto pb-1">
            <button
                @click="activeTab = 'plans'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm whitespace-nowrap transition-all cursor-pointer flex items-center gap-2',
                    activeTab === 'plans'
                        ? 'bg-violet-600 text-white shadow-md shadow-violet-600/20'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'
                ]"
            >
                <span>💳 Plan Tiers</span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] bg-black/20 text-white">{{ plans.length }}</span>
            </button>

            <button
                @click="activeTab = 'shared'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm whitespace-nowrap transition-all cursor-pointer flex items-center gap-2',
                    activeTab === 'shared'
                        ? 'bg-violet-600 text-white shadow-md shadow-violet-600/20'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'
                ]"
            >
                <span>📝 Shared Content &amp; Add-Ons</span>
            </button>

            <button
                @click="activeTab = 'included'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm whitespace-nowrap transition-all cursor-pointer flex items-center gap-2',
                    activeTab === 'included'
                        ? 'bg-violet-600 text-white shadow-md shadow-violet-600/20'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'
                ]"
            >
                <span>✨ What's Included Baseline</span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] bg-black/20 text-white">{{ includedItems.length }}</span>
            </button>

            <button
                @click="activeTab = 'valueprops'"
                :class="[
                    'px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm whitespace-nowrap transition-all cursor-pointer flex items-center gap-2',
                    activeTab === 'valueprops'
                        ? 'bg-violet-600 text-white shadow-md shadow-violet-600/20'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'
                ]"
            >
                <span>⚡ Value Strip Props</span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] bg-black/20 text-white">{{ valueProps.length }}</span>
            </button>
        </div>

        <!-- ══ TAB 1: PLAN TIERS ══════════════════════════════════════════ -->
        <div v-if="activeTab === 'plans'" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">Active Plan Tiers</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Order from left to right as displayed on the marketing cards.</p>
                </div>
                <button
                    @click="openCreatePlanModal"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold transition shadow-md shadow-violet-600/30 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Create New Plan</span>
                </button>
            </div>

            <!-- Plans Grid / Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="(plan, index) in plans"
                    :key="plan.id"
                    :class="[
                        'rounded-3xl p-6 bg-white dark:bg-slate-900 border transition-all duration-200 flex flex-col justify-between space-y-5 shadow-xs relative',
                        plan.is_highlighted
                            ? 'border-amber-400 dark:border-amber-500/80 ring-2 ring-amber-400/30 shadow-amber-500/10'
                            : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700',
                        !plan.is_active ? 'opacity-60 bg-slate-50 dark:bg-slate-950/50' : ''
                    ]"
                >
                    <!-- Highlighted Badge & Order Controls -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                #{{ index + 1 }}
                            </span>
                            <span
                                v-if="plan.is_highlighted"
                                class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-lg bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700"
                            >
                                <span>⭐ Featured Tier</span>
                            </span>
                            <span
                                :class="[
                                    'text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider',
                                    plan.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                ]"
                            >
                                {{ plan.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>

                        <!-- Ordering controls -->
                        <div class="flex items-center gap-1">
                            <button
                                @click="movePlanOrder(index, -1)"
                                :disabled="index === 0"
                                type="button"
                                class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-slate-700 dark:text-slate-300 transition"
                                title="Move left"
                            >
                                ←
                            </button>
                            <button
                                @click="movePlanOrder(index, 1)"
                                :disabled="index === plans.length - 1"
                                type="button"
                                class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-slate-700 dark:text-slate-300 transition"
                                title="Move right"
                            >
                                →
                            </button>
                        </div>
                    </div>

                    <!-- Plan Title & Tagline -->
                    <div class="space-y-1">
                        <h3 class="text-xl font-black text-slate-900 dark:text-white">
                            {{ plan.name }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                            {{ plan.tagline || 'No tagline set' }}
                        </p>
                    </div>

                    <!-- Pricing Info -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800/80 space-y-2">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs text-slate-500">First Year:</span>
                            <span class="text-base font-black text-slate-900 dark:text-white font-mono">{{ plan.first_year_price }}</span>
                        </div>
                        <div class="flex items-baseline justify-between border-t border-slate-200 dark:border-slate-800/60 pt-2">
                            <span class="text-xs text-slate-500">Annual Renewal:</span>
                            <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300 font-mono">{{ plan.renewal_price }}</span>
                        </div>
                    </div>

                    <!-- Limits & Entitlements -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-slate-100/60 dark:bg-slate-800/40">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Branches</div>
                            <div class="font-extrabold text-slate-800 dark:text-slate-200">{{ plan.branches_limit }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-100/60 dark:bg-slate-800/40">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Users</div>
                            <div class="font-extrabold text-slate-800 dark:text-slate-200">{{ plan.users_limit }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-100/60 dark:bg-slate-800/40 col-span-2">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">AI Allocation</div>
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ plan.ai_credits || 'None' }}</div>
                        </div>
                    </div>

                    <!-- Features Count Preview -->
                    <div class="text-xs text-slate-500 flex items-center justify-between pt-1">
                        <span>{{ plan.features?.length || 0 }} bullet features</span>
                        <span class="font-medium text-slate-400">{{ plan.support_level }}</span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                        <button
                            @click="openEditPlanModal(plan)"
                            type="button"
                            class="flex-1 py-2 px-3 rounded-xl bg-violet-50 hover:bg-violet-100 dark:bg-violet-950/60 dark:hover:bg-violet-900/60 text-violet-700 dark:text-violet-300 font-bold text-xs transition cursor-pointer text-center"
                        >
                            Edit Plan
                        </button>
                        <button
                            @click="togglePlanHighlight(plan)"
                            type="button"
                            :class="[
                                'py-2 px-2.5 rounded-xl font-bold text-xs transition cursor-pointer',
                                plan.is_highlighted
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                            ]"
                            :title="plan.is_highlighted ? 'Remove featured badge' : 'Make featured tier'"
                        >
                            ⭐
                        </button>
                        <button
                            @click="togglePlanActive(plan)"
                            type="button"
                            :class="[
                                'py-2 px-2.5 rounded-xl font-bold text-xs transition cursor-pointer',
                                plan.is_active
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                            ]"
                            :title="plan.is_active ? 'Hide plan from homepage' : 'Show plan on homepage'"
                        >
                            {{ plan.is_active ? '👁️' : '🙈' }}
                        </button>
                        <button
                            @click="deletePlan(plan)"
                            type="button"
                            class="py-2 px-2.5 rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-700 dark:bg-slate-800 dark:hover:bg-rose-950/60 dark:text-slate-400 dark:hover:text-rose-400 font-bold text-xs transition cursor-pointer"
                            title="Delete plan"
                        >
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ TAB 2: SHARED CONTENT & ADD-ONS ════════════════════════════ -->
        <div v-if="activeTab === 'shared'" class="space-y-6">
            <form @submit.prevent="saveSettings" class="space-y-6">
                <!-- Card 1: Section Headings & Intro -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Homepage Intro &amp; Currency</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Configure the top title, descriptive text, and monetary symbol.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Currency Symbol
                            </label>
                            <input
                                v-model="settingsForm.currency_symbol"
                                type="text"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="₦"
                                maxlength="10"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Intro Heading
                            </label>
                            <input
                                v-model="settingsForm.pricing_intro_heading"
                                type="text"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="Smart Retail Management for Growing Businesses"
                            />
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Intro Description Text
                            </label>
                            <textarea
                                v-model="settingsForm.pricing_intro_text"
                                rows="3"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="Manage sales, inventory, staff, customers and multiple branches from anywhere..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Optional Add-Ons (Markdown) -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Optional Add-Ons (Markdown)</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Listed underneath the plan cards on the homepage with bold bullets and custom pricing.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Add-Ons Section Heading
                            </label>
                            <input
                                v-model="settingsForm.addons_heading"
                                type="text"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="Optional Add-Ons &amp; Expansions"
                            />
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Markdown Content
                                </label>
                                <textarea
                                    v-model="settingsForm.addons_content"
                                    rows="7"
                                    class="w-full font-mono text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                    placeholder="- **Additional Branch Setup**: From ₦75,000..."
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                                    Live Markdown Preview
                                </label>
                                <div
                                    class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-700 dark:text-slate-300 min-h-[140px] prose dark:prose-invert max-w-none"
                                    v-html="renderMarkdown(settingsForm.addons_content)"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Important Commercial Notes (Markdown) -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Important Commercial Notes (Markdown)</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Explaining setup coverage, annual renewals, hardware independence, and licensing terms.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Notes Section Heading
                            </label>
                            <input
                                v-model="settingsForm.commercial_notes_heading"
                                type="text"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="Important Commercial Notes"
                            />
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Markdown Content
                                </label>
                                <textarea
                                    v-model="settingsForm.commercial_notes_content"
                                    rows="7"
                                    class="w-full font-mono text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                    placeholder="- **First-Year Investment**: Covers full system setup..."
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                                    Live Markdown Preview
                                </label>
                                <div
                                    class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-700 dark:text-slate-300 min-h-[140px] prose dark:prose-invert max-w-none"
                                    v-html="renderMarkdown(settingsForm.commercial_notes_content)"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="settingsForm.processing"
                        class="px-6 py-3 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-extrabold text-sm transition shadow-lg shadow-violet-600/30 cursor-pointer disabled:opacity-50"
                    >
                        Save Shared Content Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- ══ TAB 3: WHAT'S INCLUDED BASELINE ════════════════════════════ -->
        <div v-if="activeTab === 'included'" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">What Every Plan Includes</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Baseline capabilities shared across all tiers, displayed once above/below the cards.</p>
                    </div>
                    <button
                        @click="addIncludedItem"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-violet-50 dark:bg-violet-950/70 text-violet-700 dark:text-violet-300 hover:bg-violet-100 font-bold text-xs transition cursor-pointer"
                    >
                        <span>+ Add Item</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(item, idx) in includedForm.items"
                        :key="idx"
                        class="flex items-center gap-3"
                    >
                        <span class="w-6 text-center text-xs font-bold text-slate-400">
                            {{ idx + 1 }}.
                        </span>
                        <input
                            v-model="includedForm.items[idx]"
                            type="text"
                            class="flex-1 px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                            placeholder="e.g. Cloud access with secure branch based data separation"
                        />
                        <button
                            @click="removeIncludedItem(idx)"
                            type="button"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                            title="Remove item"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button
                        @click="saveIncludedItems"
                        :disabled="includedForm.processing"
                        type="button"
                        class="px-6 py-3 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-extrabold text-sm transition shadow-lg shadow-violet-600/30 cursor-pointer disabled:opacity-50"
                    >
                        Save Baseline Included Items
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ TAB 4: MARKETING VALUE PROPS ═══════════════════════════════ -->
        <div v-if="activeTab === 'valueprops'" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Marketing Value Strip Items</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">The 4-column value strip banner displayed at the top of the pricing section.</p>
                    </div>
                    <button
                        @click="addValueProp"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-violet-50 dark:bg-violet-950/70 text-violet-700 dark:text-violet-300 hover:bg-violet-100 font-bold text-xs transition cursor-pointer"
                    >
                        <span>+ Add Value Prop</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(prop, idx) in valuePropsForm.props"
                        :key="idx"
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 flex flex-col sm:flex-row items-start sm:items-center gap-4"
                    >
                        <span class="w-6 text-center text-xs font-bold text-slate-400">
                            #{{ idx + 1 }}
                        </span>
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3 w-full">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Title</label>
                                <input
                                    v-model="prop.title"
                                    type="text"
                                    class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                    placeholder="SELL SMARTER"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Subtext</label>
                                <input
                                    v-model="prop.subtext"
                                    type="text"
                                    class="w-full px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                    placeholder="Speed up checkouts and eliminate pricing errors."
                                />
                            </div>
                        </div>
                        <button
                            @click="removeValueProp(idx)"
                            type="button"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer self-end sm:self-center"
                            title="Remove value prop"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button
                        @click="saveValueProps"
                        :disabled="valuePropsForm.processing"
                        type="button"
                        class="px-6 py-3 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-extrabold text-sm transition shadow-lg shadow-violet-600/30 cursor-pointer disabled:opacity-50"
                    >
                        Save Value Strip Propositions
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ CREATE / EDIT PLAN MODAL ═══════════════════════════════════ -->
        <div
            v-if="showPlanModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-3xl w-full border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
            >
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">
                            {{ isEditingPlan ? `Edit Plan: ${planForm.name}` : 'Create New Pricing Tier' }}
                        </h3>
                        <p class="text-xs text-slate-500">Configure tier pricing, limits, and bullet features for marketing showcase.</p>
                    </div>
                    <button
                        @click="showPlanModal = false"
                        type="button"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <!-- Modal Body (Scrollable) -->
                <form @submit.prevent="submitPlanForm" class="flex-1 overflow-y-auto p-6 space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Plan Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Plan Tier Name *
                            </label>
                            <input
                                v-model="planForm.name"
                                type="text"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="e.g. Starter, Bronze, Gold"
                            />
                            <div v-if="planForm.errors.name" class="text-rose-500 text-[11px] mt-1">{{ planForm.errors.name }}</div>
                        </div>

                        <!-- Tagline -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Tagline / Subtitle
                            </label>
                            <input
                                v-model="planForm.tagline"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="For a single shop that wants a professional cloud POS..."
                            />
                        </div>

                        <!-- First Year Price -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                First Year Price (Text) *
                            </label>
                            <input
                                v-model="planForm.first_year_price"
                                type="text"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none font-mono"
                                placeholder="e.g. ₦150,000 or From ₦1,000,000"
                            />
                            <div v-if="planForm.errors.first_year_price" class="text-rose-500 text-[11px] mt-1">{{ planForm.errors.first_year_price }}</div>
                        </div>

                        <!-- Renewal Price -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Renewal Price (Text) *
                            </label>
                            <input
                                v-model="planForm.renewal_price"
                                type="text"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none font-mono"
                                placeholder="e.g. ₦100,000 / year or Custom"
                            />
                            <div v-if="planForm.errors.renewal_price" class="text-rose-500 text-[11px] mt-1">{{ planForm.errors.renewal_price }}</div>
                        </div>

                        <!-- Branches Limit -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Branches Limit *
                            </label>
                            <input
                                v-model="planForm.branches_limit"
                                type="text"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="e.g. 1 Branch, Up to 10 Branches"
                            />
                            <div v-if="planForm.errors.branches_limit" class="text-rose-500 text-[11px] mt-1">{{ planForm.errors.branches_limit }}</div>
                        </div>

                        <!-- Users Limit -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Staff Users Limit *
                            </label>
                            <input
                                v-model="planForm.users_limit"
                                type="text"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="e.g. Up to 5 Users, Unlimited"
                            />
                            <div v-if="planForm.errors.users_limit" class="text-rose-500 text-[11px] mt-1">{{ planForm.errors.users_limit }}</div>
                        </div>

                        <!-- AI Credits -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                AI Credits Allocation
                            </label>
                            <input
                                v-model="planForm.ai_credits"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="e.g. 25 AI credits monthly"
                            />
                        </div>

                        <!-- Support Level -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Support Level
                            </label>
                            <input
                                v-model="planForm.support_level"
                                type="text"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                placeholder="e.g. Standard remote support"
                            />
                        </div>
                    </div>

                    <!-- Highlighted & Active Toggles -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="planForm.is_highlighted"
                                type="checkbox"
                                class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400"
                            />
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block">⭐ Visually Emphasized (Featured)</span>
                                <span class="text-[11px] text-slate-500 block">Stands out with distinct Gold/Amber border &amp; badge</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="planForm.is_active"
                                type="checkbox"
                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500"
                            />
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block">Active Tier</span>
                                <span class="text-[11px] text-slate-500 block">Uncheck to temporarily hide from homepage</span>
                            </div>
                        </label>
                    </div>

                    <!-- Bullet Feature Points -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Plan Bullet Features
                            </label>
                            <button
                                @click="addFeatureInput"
                                type="button"
                                class="text-xs text-violet-600 dark:text-violet-400 font-bold hover:underline cursor-pointer"
                            >
                                + Add Bullet
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="(feat, fIdx) in planForm.features"
                                :key="fIdx"
                                class="flex items-center gap-2"
                            >
                                <span class="text-emerald-500 font-bold text-sm">✓</span>
                                <input
                                    v-model="planForm.features[fIdx]"
                                    type="text"
                                    class="flex-1 px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-violet-600 focus:outline-none"
                                    placeholder="e.g. Debt and customer account management"
                                />
                                <button
                                    @click="removeFeatureInput(fIdx)"
                                    type="button"
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition cursor-pointer"
                                    title="Remove bullet"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button
                            @click="showPlanModal = false"
                            type="button"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="planForm.processing"
                            class="px-6 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-xs font-extrabold transition shadow-md shadow-violet-600/30 cursor-pointer disabled:opacity-50"
                        >
                            {{ isEditingPlan ? 'Save Plan Changes' : 'Create Plan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
