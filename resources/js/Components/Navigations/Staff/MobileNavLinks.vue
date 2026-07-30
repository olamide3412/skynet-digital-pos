<script setup>

defineEmits(['close']);
const liClasses = 'block px-2 py-0 text-gray-700 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700';
</script>

<template>
  <li>
    <Link :href="route('pos.index')" :class="liClasses" @click="$emit('close')">
      Dashboard
    </Link>
  </li>

  <li>
    <Link
      :href="route('pos.logout')"
      as="button"
      method="post"
      class="block px-4 py-2 text-red-500 hover:bg-gray-100 w-full dark:hover:bg-gray-700"
      @click="$emit('close')"
    >
      Logout
    </Link>
  </li>
</template>
