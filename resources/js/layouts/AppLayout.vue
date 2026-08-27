<script setup lang="ts">
import CursorOrb from '@/common/feedback/CursorOrb.vue';
import NoiseGrain from '@/common/feedback/NoiseGrain.vue';
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
            <NoiseGrain />
            <CursorOrb />
        </AppLayout>
    </MotionRoot>
</template>
