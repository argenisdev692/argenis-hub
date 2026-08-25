<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup } from '@/modules/app/composables/useNavGroups';

const { groups } = defineProps<{
    groups: readonly NavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup
        v-for="group in groups"
        :key="group.id"
        class="px-2 py-0 group-data-[collapsible=icon]:px-1"
    >
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>

        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <!--
                  A destination whose module has not shipped renders as a
                  disabled row rather than a link: the product's shape stays
                  visible and nothing leads to a 404.
                -->
                <SidebarMenuButton
                    v-if="item.comingSoon"
                    disabled
                    :tooltip="`${item.title} — coming soon`"
                    class="cursor-default opacity-55"
                >
                    <component :is="item.icon" />
                    <span>{{ item.title }}</span>
                </SidebarMenuButton>

                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="relative transition-colors duration-200 ease-brand"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuBadge
                    v-if="item.comingSoon"
                    class="pointer-events-none text-[0.625rem] tracking-wide text-muted-foreground uppercase group-data-[collapsible=icon]:hidden"
                >
                    Soon
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
