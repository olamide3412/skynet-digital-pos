<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import dayjs from 'dayjs'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    expense: { type: Object, default: null }, // null = create mode, object = edit mode
    categories: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const page = usePage()
const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || page.props.default_branch?.slug || route().params?.branch || '')

const isEditing = computed(() => !!props.expense?.id)

const form = ref({
    amount: '',
    expense_category_id: '',
    expense_date: dayjs().format('YYYY-MM-DD'),
    description: '',
    reason: '',
})

const receiptFile = ref(null)
const receiptPreview = ref(null)
const isSubmitting = ref(false)
const errorMessage = ref('')

// Inline Category Creation
const showNewCategoryInput = ref(false)
const newCategoryName = ref('')
const isCreatingCategory = ref(false)
const localCategories = ref([...props.categories])

watch(() => props.categories, (newCats) => {
    localCategories.value = [...newCats]
}, { immediate: true })

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        errorMessage.value = ''
        receiptFile.value = null
        showNewCategoryInput.value = false
        newCategoryName.value = ''

        if (props.expense) {
            form.value = {
                amount: props.expense.amount ?? '',
                expense_category_id: props.expense.expense_category_id ?? '',
                expense_date: props.expense.expense_date ? dayjs(props.expense.expense_date).format('YYYY-MM-DD') : dayjs().format('YYYY-MM-DD'),
                description: props.expense.description ?? '',
                reason: '',
            }
            receiptPreview.value = props.expense.receipt_url || null
        } else {
            form.value = {
                amount: '',
                expense_category_id: localCategories.value[0]?.id || '',
                expense_date: dayjs().format('YYYY-MM-DD'),
                description: '',
                reason: '',
            }
            receiptPreview.value = null
        }
    }
})

function handleReceiptChange(e) {
    const file = e.target.files[0]
    if (!file) return

    receiptFile.value = file
    if (file.type.startsWith('image/')) {
        receiptPreview.value = URL.createObjectURL(file)
    } else {
        receiptPreview.value = 'pdf'
    }
}

function removeReceipt() {
    receiptFile.value = null
    receiptPreview.value = null
}

async function createCategory() {
    if (!newCategoryName.value.trim()) return
    isCreatingCategory.value = true
    errorMessage.value = ''

    try {
        const res = await axios.post(route('pos.expense-categories.store', { branch: branchSlug.value }), {
            name: newCategoryName.value.trim(),
        })

        if (res.data?.category) {
            localCategories.value.push(res.data.category)
            form.value.expense_category_id = res.data.category.id
            newCategoryName.value = ''
            showNewCategoryInput.value = false
        }
    } catch (err) {
        console.error('Failed to create category:', err)
        errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.name?.[0] || 'Failed to create category.'
    } finally {
        isCreatingCategory.value = false
    }
}

async function submitExpense() {
    if (!form.value.amount || Number(form.value.amount) <= 0) {
        errorMessage.value = 'Please enter a valid expense amount greater than 0.'
        return
    }
    if (!form.value.expense_category_id) {
        errorMessage.value = 'Please select an expense category.'
        return
    }
    if (!form.value.expense_date) {
        errorMessage.value = 'Please select an expense date.'
        return
    }
    if (isEditing.value && !form.value.reason?.trim()) {
        errorMessage.value = 'Please provide a brief reason for modifying this expense record.'
        return
    }

    isSubmitting.value = true
    errorMessage.value = ''

    const formData = new FormData()
    formData.append('amount', form.value.amount)
    formData.append('expense_category_id', form.value.expense_category_id)
    formData.append('expense_date', form.value.expense_date)
    if (form.value.description) {
        formData.append('description', form.value.description)
    }
    if (receiptFile.value) {
        formData.append('receipt', receiptFile.value)
    }

    try {
        if (isEditing.value) {
            formData.append('_method', 'PUT')
            if (form.value.reason) {
                formData.append('reason', form.value.reason)
            }
            await axios.post(
                route('pos.expenses.update', { branch: branchSlug.value, expense: props.expense.id }),
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            )
        } else {
            await axios.post(
                route('pos.expenses.store', { branch: branchSlug.value }),
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            )
        }

        emit('update:modelValue', false)
        emit('saved')
    } catch (err) {
        console.error('Failed to save expense:', err)
        errorMessage.value = err.response?.data?.message || 'Failed to save expense. Please check your inputs.'
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity animate-fade-in"
    >
        <div
            class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transform transition-all"
        >
            <!-- Header -->
            <div class="px-6 py-4.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/60 dark:bg-slate-800/60">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg">
                        🧾
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            {{ isEditing ? 'Edit Expense Record' : 'Record Operating Expense' }}
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ isEditing ? 'Update amount, category, date, or receipt' : 'Log daily branch expenses for accounting & cost tracking' }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="$emit('update:modelValue', false)"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition p-1.5 rounded-lg"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Form Content -->
            <form @submit.prevent="submitExpense" class="p-6 space-y-4">
                <!-- Error Alert -->
                <div v-if="errorMessage" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-xs text-rose-700 dark:text-rose-400 flex items-center gap-2">
                    <span>⚠️</span>
                    <span>{{ errorMessage }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Amount -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Amount *
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-xs font-bold">₦</span>
                            <input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="0.00"
                                class="w-full pl-7 pr-3 py-2 text-sm font-semibold bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Expense Date *
                        </label>
                        <input
                            v-model="form.expense_date"
                            type="date"
                            required
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                        />
                    </div>
                </div>

                <!-- Category -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Category *
                        </label>
                        <button
                            type="button"
                            @click="showNewCategoryInput = !showNewCategoryInput"
                            class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            {{ showNewCategoryInput ? 'Cancel' : '+ New Category' }}
                        </button>
                    </div>

                    <!-- Inline New Category Input -->
                    <div v-if="showNewCategoryInput" class="mb-2 flex items-center gap-2">
                        <input
                            v-model="newCategoryName"
                            type="text"
                            placeholder="e.g. Fuel / Generator"
                            class="flex-1 px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-indigo-300 dark:border-indigo-700 rounded-lg text-slate-900 dark:text-white focus:outline-none"
                        />
                        <button
                            type="button"
                            @click="createCategory"
                            :disabled="isCreatingCategory || !newCategoryName.trim()"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition disabled:opacity-50"
                        >
                            {{ isCreatingCategory ? 'Saving…' : 'Add' }}
                        </button>
                    </div>

                    <select
                        v-model="form.expense_category_id"
                        required
                        class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                        <option value="" disabled>Select category…</option>
                        <option
                            v-for="cat in localCategories"
                            :key="cat.id"
                            :value="cat.id"
                        >
                            {{ cat.name }} {{ cat.branch_id === null ? '(System Default)' : '' }}
                        </option>
                    </select>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Description / Note
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        placeholder="Context or notes regarding this expense…"
                        class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                    ></textarea>
                </div>

                <!-- Receipt Upload -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Receipt / Attachment (Optional)
                    </label>
                    <div class="flex items-center gap-3">
                        <label class="px-3.5 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold cursor-pointer transition flex items-center gap-2 border border-slate-300 dark:border-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>{{ receiptFile ? 'Change File' : 'Upload Receipt' }}</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="hidden"
                                @change="handleReceiptChange"
                            />
                        </label>
                        <span class="text-[11px] text-slate-400">JPG, PNG, WEBP or PDF (Max 5MB)</span>
                    </div>

                    <!-- Receipt Preview -->
                    <div v-if="receiptPreview" class="mt-2.5 p-2 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div v-if="receiptPreview === 'pdf'" class="w-10 h-10 bg-rose-500/10 text-rose-600 flex items-center justify-center rounded-lg font-bold text-xs">
                                PDF
                            </div>
                            <img
                                v-else
                                :src="receiptPreview"
                                alt="Receipt"
                                class="w-10 h-10 object-cover rounded-lg border border-slate-200 dark:border-slate-700"
                            />
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate max-w-[200px]">
                                {{ receiptFile?.name || 'Attached Receipt' }}
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="removeReceipt"
                            class="text-xs text-rose-600 hover:text-rose-500 font-bold px-2 py-1"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <!-- Reason for Editing (Edit mode only) -->
                <div v-if="isEditing" class="p-3 bg-amber-500/5 dark:bg-amber-400/5 border border-amber-500/20 rounded-xl">
                    <label class="block text-xs font-bold text-amber-800 dark:text-amber-300 mb-1">
                        Reason for Editing *
                    </label>
                    <input
                        v-model="form.reason"
                        type="text"
                        required
                        placeholder="e.g. Corrected amount according to bank receipt"
                        class="w-full px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 rounded-lg text-slate-900 dark:text-white focus:outline-none"
                    />
                    <p class="text-[10px] text-amber-700 dark:text-amber-400 mt-1">
                        Changes are recorded in the audit history for financial record-keeping.
                    </p>
                </div>

                <!-- Modal Actions -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        @click="$emit('update:modelValue', false)"
                        class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs rounded-xl shadow-md shadow-amber-500/20 transition disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <svg v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>{{ isSubmitting ? 'Saving…' : (isEditing ? 'Update Expense' : 'Record Expense') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
