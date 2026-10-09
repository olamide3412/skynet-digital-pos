<script setup>
import { ref, computed } from 'vue'
import { router, usePage, useForm } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import ExportDropdown from '@/Components/Accounting/ExportDropdown.vue'

defineOptions({ layout: PosLayout })

const props = defineProps({
    accounts: Array,
    filters: Object,
})

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.default_branch?.slug || '')
const canManage = computed(() => Boolean(page.props.pos_permissions?.canManageChartOfAccounts))

const search = ref(props.filters?.search ?? '')
const selectedType = ref(props.filters?.type ?? '')

function doFilter() {
    router.get(
        route('pos.accounting.accounts.index', { branch: branchSlug.value }),
        { search: search.value, type: selectedType.value },
        { preserveState: true, replace: true }
    )
}

// Add Account Modal
const showCreateModal = ref(false)
const createForm = useForm({
    code: '',
    name: '',
    type: 'expense',
    category: '',
    normal_balance: 'debit',
    description: '',
})

function submitCreate() {
    createForm.post(route('pos.accounting.accounts.store', { branch: branchSlug.value }), {
        onSuccess: () => {
            showCreateModal.value = false
            createForm.reset()
        },
    })
}

// Edit Account Modal
const showEditModal = ref(false)
const editingAccount = ref(null)
const editForm = useForm({
    name: '',
    description: '',
    is_active: true,
})

function openEdit(acc) {
    editingAccount.value = acc
    editForm.name = acc.name
    editForm.description = acc.description || ''
    editForm.is_active = Boolean(acc.is_active)
    showEditModal.value = true
}

function submitEdit() {
    if (!editingAccount.value) return
    editForm.put(route('pos.accounting.accounts.update', { branch: branchSlug.value, account: editingAccount.value.id }), {
        onSuccess: () => {
            showEditModal.value = false
            editingAccount.value = null
        },
    })
}

const typeBadges = {
    asset: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    liability: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    equity: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    revenue: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    expense: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Chart of Accounts</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage branch general ledger accounts and numbering</p>
            </div>
            <div class="flex items-center gap-2">
                <ExportDropdown
                    :base-url="route('pos.accounting.accounts.export', { branch: branchSlug })"
                    :params="{ search: search, type: type }"
                    title="Export Chart of Accounts"
                />
                <button
                    v-if="canManage"
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition font-medium flex items-center gap-1.5 shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Account
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 flex gap-2 flex-shrink-0">
            <input
                v-model="search"
                @keydown.enter="doFilter"
                type="text"
                placeholder="Search code, name or category…"
                class="w-64 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            />
            <select
                v-model="selectedType"
                @change="doFilter"
                class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 rounded-lg text-sm outline-none border border-slate-200 dark:border-slate-600 focus:border-emerald-500 transition"
            >
                <option value="">All Types</option>
                <option value="asset">Assets (1xxx)</option>
                <option value="liability">Liabilities (2xxx)</option>
                <option value="equity">Equity (3xxx)</option>
                <option value="revenue">Revenue (4xxx)</option>
                <option value="expense">Expenses & COGS (5xxx / 6xxx)</option>
            </select>
        </div>

        <!-- Accounts Table -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Code</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Account Name</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Type</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Category</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Normal Bal.</th>
                            <th class="text-left px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Status</th>
                            <th v-if="canManage" class="text-right px-4 py-3 text-slate-500 dark:text-slate-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">{{ acc.code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900 dark:text-white">{{ acc.name }}</div>
                                <div v-if="acc.description" class="text-xs text-slate-400 truncate max-w-xs">{{ acc.description }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium uppercase tracking-wider" :class="typeBadges[acc.type] || 'bg-slate-100 text-slate-800'">
                                    {{ acc.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ acc.category || '—' }}</td>
                            <td class="px-4 py-3 uppercase text-xs font-mono font-medium text-slate-500 dark:text-slate-400">{{ acc.normal_balance }}</td>
                            <td class="px-4 py-3">
                                <span v-if="acc.is_system" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 mr-1.5">
                                    System
                                </span>
                                <span :class="acc.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" class="text-xs font-medium">
                                    {{ acc.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td v-if="canManage" class="px-4 py-3 text-right">
                                <button @click="openEdit(acc)" class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 font-medium">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!accounts || accounts.length === 0">
                            <td colspan="7" class="text-center py-8 text-slate-400">No accounts found matching filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Create New Account</h3>
                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Account Code</label>
                        <input v-model="createForm.code" type="text" placeholder="e.g. 6250" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" required />
                        <span v-if="createForm.errors.code" class="text-xs text-rose-500 mt-1 block">{{ createForm.errors.code }}</span>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Account Name</label>
                        <input v-model="createForm.name" type="text" placeholder="e.g. Delivery & Logistics" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Type</label>
                            <select v-model="createForm.type" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500">
                                <option value="asset">Asset (1xxx)</option>
                                <option value="liability">Liability (2xxx)</option>
                                <option value="equity">Equity (3xxx)</option>
                                <option value="revenue">Revenue (4xxx)</option>
                                <option value="expense">Expense (6xxx)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Normal Balance</label>
                            <select v-model="createForm.normal_balance" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500">
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Category (Optional)</label>
                        <input v-model="createForm.category" type="text" placeholder="e.g. Operating Expense" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Description (Optional)</label>
                        <textarea v-model="createForm.description" rows="2" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-slate-200 dark:border-slate-600 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium">Create Account</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Edit Account: {{ editingAccount?.code }}</h3>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Account Name</label>
                        <input v-model="editForm.name" type="text" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500" required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Description</label>
                        <textarea v-model="editForm.description" rows="2" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none focus:border-emerald-500"></textarea>
                    </div>
                    <div v-if="!editingAccount?.is_system" class="flex items-center gap-2">
                        <input v-model="editForm.is_active" type="checkbox" id="edit_active" class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 dark:border-slate-600" />
                        <label for="edit_active" class="text-sm text-slate-700 dark:text-slate-300">Account is Active</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-slate-200 dark:border-slate-600 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
