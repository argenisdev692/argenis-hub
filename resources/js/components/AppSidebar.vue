<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandLogo from '@/common/brand/BrandLogo.vue';
import AppCommandMenu from '@/components/AppCommandMenu.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useNavGroups } from '@/modules/app/composables/useNavGroups';
import { dashboard } from '@/routes';

const navGroups = useNavGroups();
const appName = computed(() => usePage().props.name);

const commandOpen = ref(false);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="gap-2">
            <SidebarMenu>
                <!--
                  A plain Link rather than SidebarMenuButton, which every other
                  row in the sidebar uses.

                  Two of that component's base classes are wrong for a logo and
                  cannot be undone from here: `overflow-hidden` shears the bloom
                  off the mark's drop shadow at the button's padding box, and
                  `[&>span:last-child]:truncate` exists to ellipsise the app
                  name — which is now part of the image, not a text node beside
                  it. The collapse behaviour it would have supplied is two
                  utility classes, so the trade is one-sided.
                -->
                <SidebarMenuItem>
                    <Link
                        :href="dashboard()"
                        class="flex h-12 items-center rounded-md px-2 outline-hidden group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0 focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                    >
                        <!-- The wordmark is 4:1 and has nowhere to go at icon
                             width, so the square glyph takes over there. -->
                        <BrandLogo
                            asset="mark"
                            :alt="appName"
                            class="hidden size-8 group-data-[collapsible=icon]:block"
                        />
                        <BrandLogo
                            :alt="appName"
                            class="h-7 group-data-[collapsible=icon]:hidden"
                        />
                    </Link>
                </SidebarMenuItem>
            </SidebarMenu>

            <!--
              Search entry point. It renders as a button rather than an input
              because it opens the palette instead of filtering in place — an
              input that does not accept typing is a lie.
            -->
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        tooltip="Search — Ctrl K"
                        class="text-muted-foreground"
                        @click="commandOpen = true"
                    >
                        <Search />
                        <span class="flex-1 text-left">Search</span>
                        <kbd
                            class="pointer-events-none rounded border border-sidebar-border px-1.5 font-mono text-[0.625rem] group-data-[collapsible=icon]:hidden"
                        >
                            ⌘K
                        </kbd>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain :groups="navGroups" />
        </SidebarContent>
    </Sidebar>

    <AppCommandMenu v-model:open="commandOpen" />

    <slot />
</template>
