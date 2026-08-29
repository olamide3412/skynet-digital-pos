<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    broadcasts: Object,
    stats:      Object,
    branches:   Array,
    roles:      Array,
    mailConfig: Object,
    filters:    Object,
})

const showComposeModal = ref(false)
const showLogsModal = ref(false)
const selectedBroadcast = ref(null)

const form = useForm({
    title:       '',
    message:     '',
    target_type: 'all', // all | branch | branches | role | branch_role
    target_data: {
        branch_id:  '',
        branch_ids: [],
        roles:      [],
    },
    channels:    ['in_app'],
})

const search = ref(props.filters.search || '')

function onSearch() {
    router.get(
        route('superadmin.broadcasts.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true }
    )
}

function openCompose() {
    form.reset()
    form.channels = ['in_app']
    if (props.mailConfig.is_enabled) {
        form.channels.push('email')
    }
    showComposeModal.value = true
}

function submitBroadcast() {
    form.post(route('superadmin.broadcasts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showComposeModal.value = false
            form.reset()
        }
    })
}

function viewLogs(item) {
    selectedBroadcast.value = item
    showLogsModal.value = true
}

function toggleBranchSelection(branchId) {
    const idx = form.target_data.branch_ids.indexOf(branchId)
    if (idx === -1) {
        form.target_data.branch_ids.push(branchId)
    } else {
        form.target_data.branch_ids.splice(idx, 1)
    }
}

function toggleRoleSelection(roleKey) {
    const idx = form.target_data.roles.indexOf(roleKey)
    if (idx === -1) {
        form.target_data.roles.push(roleKey)
    } else {
        form.target_data.roles.splice(idx, 1)
    }
}

function getTargetBadge(item) {
    switch (item.target_type) {
        case 'all':
            return { label: 'System-wide (All)', color: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/30' }
        case 'branch':
            return { label: 'Single Branch', color: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' }
        case 'branches':
            const count = (item.target_data?.branch_ids || []).length
            return { label: `${count} Branches`, color: 'bg-teal-500/15 text-teal-700 dark:text-teal-300 border-teal-500/30' }
        case 'role':
            return { label: 'Specific Roles', color: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30' }
        case 'branch_role':
            return { label: 'Role in Branch(es)', color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' }
        default:
            return { label: item.target_type, color: 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30' }
    }
}
</script>

<template>
    <Head title="Broadcast Notifications" />

    <div class="flex-1 flex flex-col overflow-y-auto bg-slate-100 dark:bg-slate-950 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl w-full mx-auto space-y-6">

            <!-- ── Top Header ────────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                        <span>Super Admin</span>
                        <span>/</span>
                        <span class="text-slate-700 dark:text-slate-300">Broadcasts</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-3">
                        <span>Broadcast Notifications</span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Compose and dispatch announcements across branches via in-app notification bells and email.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('superadmin.settings.index')"
                        class="px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-theme" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>Mail Settings</span>
                        <span
                            :class="[
                                'w-2 h-2 rounded-full',
                                mailConfig.is_enabled ? 'bg-emerald-500' : 'bg-amber-500'
                            ]"
                            :title="mailConfig.is_enabled ? 'SMTP Configured' : 'SMTP Disabled'"
                        ></span>
                    </Link>

                    <button
                        @click="openCompose"
                        type="button"
                        class="px-4 py-2 text-xs font-bold text-white bg-theme hover:bg-theme-dark rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Compose Broadcast</span>
                    </button>
                </div>
            </div>

            <!-- ── Summary Statistics Cards ──────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl flex-shrink-0">
                        📣
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Total Broadcasts</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.total_broadcasts }}</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl flex-shrink-0">
                        🔔
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">In-App Delivered</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.in_app_delivered }}</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl flex-shrink-0">
                        ✉️
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Emails Sent</div>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.emails_sent }}</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl flex-shrink-0">
                        ⚠️
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Failed / No Email</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.emails_failed }}</div>
                    </div>
                </div>
            </div>

            <!-- ── Search & Filter Bar ───────────────────────────────────── -->
            <div class="p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between gap-3">
                <div class="relative flex-1 max-w-md">
                    <input
                        v-model="search"
                        @keyup.enter="onSearch"
                        type="text"
                        placeholder="Search broadcasts by title or message..."
                        class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-theme"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- ── Broadcasts History Table ──────────────────────────────── -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-850/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Broadcast Title & Message</th>
                                <th class="py-3 px-4">Target Audience</th>
                                <th class="py-3 px-4">Channels</th>
                                <th class="py-3 px-4">Delivery Metrics</th>
                                <th class="py-3 px-4">Sender & Date</th>
                                <th class="py-3 px-4 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/70 text-xs text-slate-700 dark:text-slate-300">
                            <template v-if="broadcasts.data && broadcasts.data.length > 0">
                                <tr
                                    v-for="item in broadcasts.data"
                                    :key="item.id"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition"
                                >
                                    <!-- Title & Message -->
                                    <td class="py-3.5 px-4 max-w-xs sm:max-w-sm">
                                        <div class="font-bold text-slate-900 dark:text-white truncate">
                                            {{ item.title }}
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">
                                            {{ item.message }}
                                        </p>
                                    </td>

                                    <!-- Target Badge -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'text-[10px] font-bold px-2 py-0.5 rounded-full border',
                                                getTargetBadge(item).color
                                            ]"
                                        >
                                            {{ getTargetBadge(item).label }}
                                        </span>
                                    </td>

                                    <!-- Channels -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                v-if="(item.channels || []).includes('in_app')"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-500/20"
                                            >
                                                In-App
                                            </span>
                                            <span
                                                v-if="(item.channels || []).includes('email')"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20"
                                            >
                                                Email
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Delivery Metrics -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="space-y-0.5 text-[11px]">
                                            <div>
                                                <strong class="text-slate-900 dark:text-white">{{ item.in_app_count }}</strong> in-app delivered
                                            </div>
                                            <div v-if="(item.channels || []).includes('email')" class="text-slate-400">
                                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ item.email_sent_count }} sent</span>
                                                <span v-if="item.email_failed_count > 0" class="text-rose-500 font-bold ml-1">• {{ item.email_failed_count }} failed</span>
                                                <span v-if="item.no_email_count > 0" class="text-amber-500 ml-1">• {{ item.no_email_count }} no email</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Sender & Date -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="text-slate-900 dark:text-white font-semibold">
                                            {{ item.sender?.name || 'Super Admin' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ new Date(item.created_at).toLocaleString() }}
                                        </div>
                                    </td>

                                    <!-- Details Button -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <button
                                            @click="viewLogs(item)"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition cursor-pointer"
                                        >
                                            View Logs
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty State -->
                            <tr v-else>
                                <td colspan="6" class="py-12 px-4 text-center">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2 text-xl shadow-inner">
                                        📣
                                    </div>
                                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">No broadcasts recorded</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Click "Compose Broadcast" to dispatch announcements to your branches.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="broadcasts.links && broadcasts.links.length > 3" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <p class="text-xs text-slate-400">
                        Showing {{ broadcasts.from || 0 }} to {{ broadcasts.to || 0 }} of {{ broadcasts.total }} broadcasts
                    </p>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in broadcasts.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                    link.active
                                        ? 'bg-theme text-white'
                                        : 'bg-white dark:bg-slate-850 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 rounded-lg text-xs text-slate-400 opacity-50 select-none"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── COMPOSE BROADCAST MODAL ──────────────────────────────── -->
            <div
                v-if="showComposeModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
                @click.self="showComposeModal = false"
            >
                <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-2xl w-full p-6 space-y-5 my-8">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-theme/10 text-theme flex items-center justify-center text-lg">
                                📣
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">Compose Broadcast Notification</h3>
                                <p class="text-xs text-slate-400">Broadcast messages directly to branches and staff.</p>
                            </div>
                        </div>
                        <button
                            @click="showComposeModal = false"
                            class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg transition cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitBroadcast" class="space-y-4">
                        <!-- Target Audience -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Target Audience
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="form.target_type = 'all'"
                                    :class="[
                                        'p-2.5 rounded-xl border text-xs font-bold text-left transition cursor-pointer',
                                        form.target_type === 'all'
                                            ? 'border-theme bg-theme-light dark:bg-theme/20 text-theme-dark dark:text-white ring-1 ring-theme'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'
                                    ]"
                                >
                                    <div>🌐 System-wide</div>
                                    <div class="text-[10px] font-normal opacity-75">All users across all branches</div>
                                </button>

                                <button
                                    type="button"
                                    @click="form.target_type = 'role'; form.target_data.roles = ['branch-admin']"
                                    :class="[
                                        'p-2.5 rounded-xl border text-xs font-bold text-left transition cursor-pointer',
                                        form.target_type === 'role'
                                            ? 'border-theme bg-theme-light dark:bg-theme/20 text-theme-dark dark:text-white ring-1 ring-theme'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'
                                    ]"
                                >
                                    <div>👔 Specific Roles</div>
                                    <div class="text-[10px] font-normal opacity-75">Managers or Cashiers</div>
                                </button>

                                <button
                                    type="button"
                                    @click="form.target_type = 'branch'"
                                    :class="[
                                        'p-2.5 rounded-xl border text-xs font-bold text-left transition cursor-pointer',
                                        form.target_type === 'branch'
                                            ? 'border-theme bg-theme-light dark:bg-theme/20 text-theme-dark dark:text-white ring-1 ring-theme'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'
                                    ]"
                                >
                                    <div>🏢 Single Branch</div>
                                    <div class="text-[10px] font-normal opacity-75">Target one location</div>
                                </button>

                                <button
                                    type="button"
                                    @click="form.target_type = 'branches'"
                                    :class="[
                                        'p-2.5 rounded-xl border text-xs font-bold text-left transition cursor-pointer',
                                        form.target_type === 'branches'
                                            ? 'border-theme bg-theme-light dark:bg-theme/20 text-theme-dark dark:text-white ring-1 ring-theme'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'
                                    ]"
                                >
                                    <div>🏘️ Multiple Branches</div>
                                    <div class="text-[10px] font-normal opacity-75">Multi-select branches</div>
                                </button>
                            </div>
                        </div>

                        <!-- Target Parameter: Single Branch Selector -->
                        <div v-if="form.target_type === 'branch'" class="p-3 bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Select Target Branch</label>
                            <select
                                v-model="form.target_data.branch_id"
                                required
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                <option value="" disabled>-- Select a Branch --</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">
                                    {{ b.name }} ({{ b.users_count }} users)
                                </option>
                            </select>
                        </div>

                        <!-- Target Parameter: Multiple Branches Multi-Select -->
                        <div v-if="form.target_type === 'branches'" class="p-3 bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Select Branches ({{ form.target_data.branch_ids.length }} selected)</label>
                                <button
                                    type="button"
                                    @click="form.target_data.branch_ids = branches.map(b => b.id)"
                                    class="text-[11px] font-bold text-theme hover:underline cursor-pointer"
                                >
                                    Select All
                                </button>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-36 overflow-y-auto pr-1">
                                <label
                                    v-for="b in branches"
                                    :key="b.id"
                                    class="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs cursor-pointer hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="form.target_data.branch_ids.includes(b.id)"
                                        @change="toggleBranchSelection(b.id)"
                                        class="rounded border-slate-300 text-theme focus:ring-theme"
                                    />
                                    <span class="truncate font-semibold text-slate-800 dark:text-slate-200">{{ b.name }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Target Parameter: Role Selector -->
                        <div v-if="form.target_type === 'role'" class="p-3 bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Select Target Roles</label>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="r in roles"
                                    :key="r.key"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs cursor-pointer hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="form.target_data.roles.includes(r.key)"
                                        @change="toggleRoleSelection(r.key)"
                                        class="rounded border-slate-300 text-theme focus:ring-theme"
                                    />
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ r.name }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Title Input -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Notification Title
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                required
                                placeholder="e.g. Scheduled System Maintenance on Sunday 2:00 AM"
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-theme"
                            />
                        </div>

                        <!-- Message Body -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Message Content
                            </label>
                            <textarea
                                v-model="form.message"
                                rows="4"
                                required
                                placeholder="Write the full announcement message here..."
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-theme leading-relaxed"
                            ></textarea>
                        </div>

                        <!-- Delivery Channels -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Delivery Channels
                            </label>
                            <div class="flex flex-wrap items-center gap-4">
                                <label class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        value="in_app"
                                        v-model="form.channels"
                                        class="rounded border-slate-300 text-theme focus:ring-theme"
                                    />
                                    <span>In-App Notification Bell</span>
                                </label>

                                <label
                                    :class="[
                                        'flex items-center gap-2 text-xs font-bold',
                                        mailConfig.is_enabled
                                            ? 'text-slate-800 dark:text-slate-200 cursor-pointer'
                                            : 'text-slate-400 dark:text-slate-500 cursor-not-allowed opacity-60'
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        value="email"
                                        v-model="form.channels"
                                        :disabled="!mailConfig.is_enabled"
                                        class="rounded border-slate-300 text-theme focus:ring-theme"
                                    />
                                    <span>Email Delivery</span>
                                    <span v-if="!mailConfig.is_enabled" class="text-[10px] font-normal text-amber-500">
                                        (SMTP disabled in Mail Settings)
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="showComposeModal = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-5 py-2 text-xs font-bold text-white bg-theme hover:bg-theme-dark rounded-xl shadow-md transition cursor-pointer flex items-center gap-2 disabled:opacity-50"
                            >
                                <svg v-if="form.processing" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>{{ form.processing ? 'Dispatching...' : 'Dispatch Broadcast' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── DELIVERY LOGS MODAL ──────────────────────────────────── -->
            <div
                v-if="showLogsModal && selectedBroadcast"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
                @click.self="showLogsModal = false"
            >
                <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-xl w-full p-6 space-y-4 my-8">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">Delivery Details</h3>
                            <p class="text-xs text-slate-400">Broadcast #{{ selectedBroadcast.id }} — {{ selectedBroadcast.title }}</p>
                        </div>
                        <button
                            @click="showLogsModal = false"
                            class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg transition cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-850">
                                <div class="text-[10px] text-slate-400">In-App</div>
                                <div class="font-black text-slate-900 dark:text-white text-base">{{ selectedBroadcast.in_app_count }}</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600">
                                <div class="text-[10px]">Emails Sent</div>
                                <div class="font-black text-base">{{ selectedBroadcast.email_sent_count }}</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/20 text-rose-600">
                                <div class="text-[10px]">Failed</div>
                                <div class="font-black text-base">{{ selectedBroadcast.email_failed_count }}</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/20 text-amber-600">
                                <div class="text-[10px]">No Email</div>
                                <div class="font-black text-base">{{ selectedBroadcast.no_email_count }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Message Body</label>
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-850 text-xs text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed max-h-32 overflow-y-auto">
                                {{ selectedBroadcast.message }}
                            </div>
                        </div>

                        <div v-if="selectedBroadcast.delivery_logs && selectedBroadcast.delivery_logs.length > 0">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Delivery Issues / Notes</label>
                            <div class="p-3 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 text-[11px] text-rose-700 dark:text-rose-300 space-y-1.5 max-h-36 overflow-y-auto">
                                <div v-for="(log, li) in selectedBroadcast.delivery_logs" :key="li" class="flex items-start gap-2">
                                    <span>⚠️</span>
                                    <div>
                                        <strong v-if="log.user_name">{{ log.user_name }}:</strong>
                                        {{ log.message || log.error }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            @click="showLogsModal = false"
                            class="px-4 py-2 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 rounded-xl transition cursor-pointer"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
