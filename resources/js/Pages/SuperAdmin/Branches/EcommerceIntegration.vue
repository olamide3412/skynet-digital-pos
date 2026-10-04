<script setup>
import { Head, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import EcommerceTab from './Partials/EcommerceTab.vue'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    branch: Object,
    commerce_integration: Object,
    ecommerce_capability_enabled: Boolean,
    commerce_api_base_url: String,
    new_commerce_token: String,
})
</script>

<template>
    <Head :title="`${branch.name} - E-Commerce Integration`" />

    <div class="space-y-6">
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <Link :href="route('superadmin.branches.index')" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Branches
                    </Link>
                    <span>/</span>
                    <Link :href="route('superadmin.branches.show', branch.slug)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        {{ branch.name }}
                    </Link>
                    <span>/</span>
                    <span class="text-slate-700 dark:text-slate-200 font-semibold">E-Commerce Integration</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white">
                    E-Commerce Connection Management
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Manage independent web store synchronization and live inventory reservation for <strong>{{ branch.name }}</strong>.
                </p>
            </div>

            <Link
                :href="route('superadmin.branches.show', branch.slug) + '?tab=ecommerce'"
                class="px-4 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 transition shrink-0 flex items-center gap-1.5 cursor-pointer"
            >
                <span>← Back to Branch Overview</span>
            </Link>
        </div>

        <!-- Ecommerce Tab Content -->
        <EcommerceTab
            :branch="branch"
            :commerce-integration="commerce_integration"
            :ecommerce-capability-enabled="ecommerce_capability_enabled"
            :commerce-api-base-url="commerce_api_base_url"
            :new-commerce-token="new_commerce_token"
        />
    </div>
</template>
