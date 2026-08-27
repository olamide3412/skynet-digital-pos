<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branch: Object,
    roles: Array,           // [{ id, name, permissions: ['canAccessPos', ...] }]
    all_permissions: Array, // [{ name, label }]
})

// Build a reactive form for each role
const forms = ref(
    props.roles.map(role => ({
        roleId:   role.id,
        roleName: role.name,
        selected: [...role.permissions],
        dirty:    false,
        saving:   false,
        success:  false,
    }))
)

const toggle = (form, permName) => {
    const idx = form.selected.indexOf(permName)
    if (idx === -1) {
        form.selected.push(permName)
    } else {
        form.selected.splice(idx, 1)
    }
    form.dirty = true
    form.success = false
}

const hasAll = (form) => form.selected.length === props.all_permissions.length
const toggleAll = (form) => {
    if (hasAll(form)) {
        form.selected = []
    } else {
        form.selected = props.all_permissions.map(p => p.name)
    }
    form.dirty = true
    form.success = false
}

const save = (form) => {
    form.saving = true
    router.put(
        route('superadmin.branches.roles.update', { branch: props.branch.slug, role: form.roleId }),
        { permissions: form.selected },
        {
            preserveScroll: true,
            onSuccess: () => {
                form.dirty   = false
                form.success = true
                form.saving  = false
                setTimeout(() => { form.success = false }, 3000)
            },
            onError: () => { form.saving = false },
        }
    )
}

const roleColor = (name) => ({
    'branch-admin': 'indigo',
    cashier: 'emerald',
})[name] ?? 'slate'
</script>

<template>
    <Head :title="`Role Permissions — ${branch.name}`" />
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <Link :href="route('superadmin.branches.users.index', { branch: branch.slug })"
                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline mb-1 inline-flex items-center gap-1 font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to Users
                </Link>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Role Permissions Template</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ branch.name }} · {{ branch.slug }}</p>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
            class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 rounded-xl px-4 py-3 text-sm font-medium">
            {{ $page.props.flash.success }}
        </div>

        <!-- Explanation -->
        <div class="bg-blue-50 dark:bg-slate-800/40 border border-blue-100 dark:border-slate-700/50 rounded-2xl p-4 text-sm text-slate-600 dark:text-slate-400 shadow-xs">
            <span class="text-slate-900 dark:text-slate-200 font-bold">How this works:</span>
            Each role (e.g. <em>cashier</em>) carries a set of permissions that determine what its members can do in the POS.
            Tick or untick permissions below and click <strong>Save Permissions</strong> to apply to all users with that role.
            <br><span class="text-amber-600 dark:text-amber-400 mt-1 inline-block font-semibold">⚠ branch-admin should generally keep all permissions.</span>
        </div>

        <!-- Role Cards -->
        <div v-for="form in forms" :key="form.roleId"
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-5 shadow-xs">

            <!-- Role header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center space-x-3">
                    <span
                        :class="{
                            'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/30': roleColor(form.roleName) === 'indigo',
                            'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30': roleColor(form.roleName) === 'emerald',
                            'bg-slate-100 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-500/30': roleColor(form.roleName) === 'slate',
                        }"
                        class="px-3.5 py-1 rounded-full text-xs font-bold border uppercase tracking-wider">
                        {{ form.roleName }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ form.selected.length }} / {{ all_permissions.length }} permissions active</span>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Select all toggle -->
                    <button @click="toggleAll(form)"
                        class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-800 cursor-pointer">
                        {{ hasAll(form) ? 'Deselect All' : 'Select All' }}
                    </button>
                    <!-- Save button -->
                    <button
                        @click="save(form)"
                        :disabled="!form.dirty || form.saving"
                        :class="form.dirty
                            ? 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 cursor-pointer'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed border border-slate-200 dark:border-slate-700'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition disabled:opacity-60">
                        {{ form.saving ? 'Saving…' : form.success ? '✓ Saved' : 'Save Permissions' }}
                    </button>
                </div>
            </div>

            <!-- Permission grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <label
                    v-for="perm in all_permissions"
                    :key="perm.name"
                    class="flex items-start space-x-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                    :class="form.selected.includes(perm.name)
                        ? 'bg-indigo-50/70 dark:bg-indigo-600/10 border-indigo-200 dark:border-indigo-500/40 text-slate-900 dark:text-slate-100 font-semibold shadow-2xs'
                        : 'bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700'"
                >
                    <div class="mt-0.5 flex-shrink-0">
                        <div
                            class="w-4 h-4 rounded border-2 flex items-center justify-center transition-all"
                            :class="form.selected.includes(perm.name)
                                ? 'bg-indigo-600 border-indigo-600'
                                : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800'"
                            @click="toggle(form, perm.name)"
                        >
                            <svg v-if="form.selected.includes(perm.name)"
                                xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                    <div @click="toggle(form, perm.name)" class="flex-1 min-w-0">
                        <div class="text-xs font-semibold leading-tight">{{ perm.label }}</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">{{ perm.name }}</div>
                    </div>
                </label>
            </div>

            <!-- Dirty indicator -->
            <p v-if="form.dirty" class="text-xs text-amber-600 dark:text-amber-400 font-semibold">
                ⚠ Unsaved changes — click "Save Permissions" to apply.
            </p>
        </div>

    </div>
</template>
