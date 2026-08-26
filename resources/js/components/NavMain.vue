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

                <!--
                  The active row carries three cues at once — gradient rail,
                  brand wash, weight — because at icon-collapse width the label
                  disappears and a colour shift alone stops being legible.
                  The rail grows from 0 on hover, so pointer feedback and the
                  current destination share one visual language.
                -->
                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="relative overflow-hidden rounded-lg transition-[background-color,color,box-shadow] duration-200 ease-brand before:absolute before:top-1/2 before:left-0 before:h-0 before:w-[3px] before:-translate-y-1/2 before:rounded-r-full before:bg-brand-gradient before:transition-[height] before:duration-200 before:ease-brand hover:before:h-3 data-[active=true]:nav-active-surface data-[active=true]:font-semibold data-[active=true]:text-sidebar-accent-foreground data-[active=true]:shadow-soft data-[active=true]:ring-1 data-[active=true]:ring-sidebar-border data-[active=true]:ring-inset data-[active=true]:before:h-5"
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
