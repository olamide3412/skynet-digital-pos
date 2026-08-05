<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const branchSlug = computed(() => page.props.current_branch?.slug || 'felix-enterprise');
const liClasses = 'block px-4 py-2 text-gray-700 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700';
</script>
<template>
    <!-- Admin Dropdown Menu -->
    <li class="relative group">
        <button class="px-0 py-0 transition-all">
            Menu
        </button>
        <!-- Dropdown menu -->
        <ul class="absolute bg-white dark:bg-gray-900 right-0 mt-0 w-48 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-opacity duration-200 z-50">
            <li>
                <Link :href="route('pos.index', { branch: branchSlug })" :class="liClasses">
                    Dashboard
                </Link>
            </li>

            <li>
                <Link :href="route('pos.categories.index', { branch: branchSlug })" :class="liClasses">
                    Categories
                </Link>
            </li>
            <li>
                <Link :href="route('pos.items.index', { branch: branchSlug })" :class="liClasses">
                    Items
                </Link>
            </li>
            <li>
                <Link :href="route('pos.logout', { branch: branchSlug })" as="button" method="post"
                    class="block px-4 py-2 text-red-500 hover:bg-gray-100 w-full text-left dark:hover:bg-gray-700">
                    Logout
                </Link>
            </li>
        </ul>
    </li>
</template>
