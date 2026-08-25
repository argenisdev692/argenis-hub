<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

const { variant = 'sidebar', class: className } = defineProps<{
    variant?: AppVariant;
    class?: string;
}>();
</script>

<template>
    <SidebarInset v-if="variant === 'sidebar'" :class="className">
        <slot />
    </SidebarInset>
    <main
        v-else
        class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
        :class="className"
    >
        <slot />
    </main>
</template>
