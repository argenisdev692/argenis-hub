<script setup lang="ts">
import CursorOrb from '@/common/feedback/CursorOrb.vue';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
        <CursorOrb />
    </AuthLayout>
</template>
