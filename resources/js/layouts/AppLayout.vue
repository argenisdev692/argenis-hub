<script setup lang="ts">
import CursorOrb from '@/common/feedback/CursorOrb.vue';
import MotionRoot from '@/common/motion/MotionRoot.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <MotionRoot>
        <AppLayout :breadcrumbs="breadcrumbs">
            <slot />
            <CursorOrb />
        </AppLayout>
    </MotionRoot>
</template>
