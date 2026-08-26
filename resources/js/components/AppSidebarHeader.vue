<script setup lang="ts">
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-glass-border bg-surface-glass px-6 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <Breadcrumbs
                v-if="breadcrumbs.length > 0"
                :breadcrumbs="breadcrumbs"
            />
        </div>

        <!--
          Account controls live here, right of the theme toggle: the two
          together are the shell's "who am I / how does it look" cluster, and
          the sidebar footer no longer competes with them.
        -->
        <div class="flex items-center gap-1.5">
            <ThemeToggle />

            <Separator
                orientation="vertical"
                class="mx-0.5 h-5! bg-glass-border"
            />

            <UserMenu />
        </div>
    </header>
</template>
