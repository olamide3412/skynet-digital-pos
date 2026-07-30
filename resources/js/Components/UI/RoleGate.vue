<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    roles: {
        type: [String, Array],
        required: true
    }
})

const page = usePage()
const userRoles = computed(() => {
    return page.props.auth.user?.roles?.map(r => r.name.toLowerCase()) || []
})

const hasAccess = computed(() => {
    // If the user is admin, allow access regardless.
    if (userRoles.value.includes('admin')) return true

    const requiredRoles = Array.isArray(props.roles) ? props.roles : [props.roles]
    const requiredRolesLower = requiredRoles.map(r => r.toLowerCase())

    return requiredRolesLower.some(r => userRoles.value.includes(r))
})
</script>

<template>
    <slot v-if="hasAccess" />
</template>
