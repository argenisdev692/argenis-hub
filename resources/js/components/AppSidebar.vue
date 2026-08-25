<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppCommandMenu from '@/components/AppCommandMenu.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
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
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <span
                                class="flex aspect-square size-8 items-center justify-center rounded-lg bg-brand-gradient"
                            >
                                <AppLogoIcon
                                    class="size-4 fill-current text-white"
                                />
                            </span>
                            <span
                                class="truncate font-semibold tracking-tight"
                                >{{ appName }}</span
                            >
                        </Link>
                    </SidebarMenuButton>
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

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <AppCommandMenu v-model:open="commandOpen" />

    <slot />
</template>
