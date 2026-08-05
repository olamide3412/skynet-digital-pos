<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

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
        route('superadmin.branches.roles.update', [props.branch.slug, form.roleId]),
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
    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans p-6">
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('superadmin.branches.users.index', branch.slug)"
                        class="text-xs text-indigo-400 hover:text-indigo-300 mb-1 inline-block">
                        &larr; Back to Users
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-100">Role Permissions</h1>
                    <p class="text-xs text-slate-400 font-mono">{{ branch.name }} · {{ branch.slug }}</p>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success"
                class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl px-4 py-3 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <!-- Explanation -->
            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-4 text-sm text-slate-400">
                <span class="text-slate-200 font-semibold">How this works:</span>
                Each role (e.g. <em>cashier</em>) carries a set of permissions that determine what its members can do in the POS.
                Tick or untick permissions below and click <strong>Save</strong> to apply to all users with that role.
                <br><span class="text-amber-400 mt-1 inline-block">⚠ branch-admin should generally keep all permissions.</span>
            </div>

            <!-- Role Cards -->
            <div v-for="form in forms" :key="form.roleId"
                class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 shadow-xl">

                <!-- Role header -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="flex items-center space-x-3">
                        <span
                            :class="{
                                'bg-indigo-500/10 text-indigo-300 border-indigo-500/30': roleColor(form.roleName) === 'indigo',
                                'bg-emerald-500/10 text-emerald-300 border-emerald-500/30': roleColor(form.roleName) === 'emerald',
                                'bg-slate-500/10 text-slate-300 border-slate-500/30': roleColor(form.roleName) === 'slate',
                            }"
                            class="px-3 py-1 rounded-full text-sm font-bold border uppercase tracking-wider">
                            {{ form.roleName }}
                        </span>
                        <span class="text-xs text-slate-500">{{ form.selected.length }} / {{ all_permissions.length }} permissions</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <!-- Select all toggle -->
                        <button @click="toggleAll(form)"
                            class="text-xs text-slate-400 hover:text-slate-200 transition-colors px-2 py-1 border border-slate-700 rounded-lg">
                            {{ hasAll(form) ? 'Deselect All' : 'Select All' }}
                        </button>
                        <!-- Save button -->
                        <button
                            @click="save(form)"
                            :disabled="!form.dirty || form.saving"
                            :class="form.dirty
                                ? 'bg-indigo-600 hover:bg-indigo-500 text-white cursor-pointer'
                                : 'bg-slate-800 text-slate-500 cursor-not-allowed'"
                            class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-colors disabled:opacity-60">
                            {{ form.saving ? 'Saving…' : form.success ? '✓ Saved' : 'Save Permissions' }}
                        </button>
                    </div>
                </div>

                <!-- Permission grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label
                        v-for="perm in all_permissions"
                        :key="perm.name"
                        class="flex items-start space-x-3 p-3 rounded-xl border cursor-pointer transition-all"
                        :class="form.selected.includes(perm.name)
                            ? 'bg-indigo-600/10 border-indigo-500/40 text-slate-100'
                            : 'bg-slate-950/50 border-slate-800 text-slate-400 hover:border-slate-600'"
                    >
                        <div class="mt-0.5 flex-shrink-0">
                            <div
                                class="w-4 h-4 rounded border-2 flex items-center justify-center transition-all"
                                :class="form.selected.includes(perm.name)
                                    ? 'bg-indigo-500 border-indigo-500'
                                    : 'border-slate-600 bg-transparent'"
                                @click="toggle(form, perm.name)"
                            >
                                <svg v-if="form.selected.includes(perm.name)"
                                    xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div @click="toggle(form, perm.name)" class="flex-1 min-w-0">
                            <div class="text-sm font-medium leading-tight">{{ perm.label }}</div>
                            <div class="text-xs text-slate-500 font-mono mt-0.5">{{ perm.name }}</div>
                        </div>
                    </label>
                </div>

                <!-- Dirty indicator -->
                <p v-if="form.dirty" class="text-xs text-amber-400">
                    ⚠ Unsaved changes — click "Save Permissions" to apply.
                </p>
            </div>

        </div>
    </div>
</template>
