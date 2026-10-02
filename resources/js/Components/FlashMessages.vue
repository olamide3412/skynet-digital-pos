<script setup>
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

// Watch for flash messages and show toasts
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.success(flash.success);
        // Clear the flash message after displaying
        page.props.flash.success = null;
    }

    if (flash?.error) {
        toast.error(flash.error);
        page.props.flash.error = null;
    }

    if (flash?.message) {
        toast.info(flash.message);
        page.props.flash.message = null;
    }
}, { immediate: true, deep: true });

// Also watch for validation or backend action errors (e.g. role/user deletion errors)
watch(() => page.props.errors, (errors) => {
    if (errors && typeof errors === 'object' && Object.keys(errors).length > 0) {
        // Only trigger if no flash error already handled it
        if (!page.props.flash?.error) {
            const firstError = Object.values(errors)[0];
            if (firstError && typeof firstError === 'string') {
                toast.error(firstError);
            }
        }
    }
}, { immediate: true, deep: true });
</script>

<template>
    <!-- This component doesn't render anything visually -->
    <!-- It just handles the flash messages -->
</template>
