<script setup lang="ts">
import CursorOrb from '@/common/feedback/CursorOrb.vue';
import NoiseGrain from '@/common/feedback/NoiseGrain.vue';
import MotionRoot from '@/common/motion/MotionRoot.vue';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <!--
        Nothing under this layout animates yet, and `MotionRoot` is here anyway.

        The `m.*` components need the feature bundle `MotionRoot` provides. With
        no provider above them they still apply their `initial` state — so a
        component that reveals from `hidden` renders at `opacity: 0` and simply
        never comes back. Shared pieces like `BarChart` and `DashboardPanel` now
        animate, and the next person to drop one onto a login or password-reset
        screen would meet a blank panel with nothing in the console to explain
        it. Two context providers are a cheap way to make that impossible.
    -->
    <MotionRoot>
        <AuthLayout :title="title" :description="description">
            <slot />
            <NoiseGrain />
            <CursorOrb />
        </AuthLayout>
    </MotionRoot>
</template>
