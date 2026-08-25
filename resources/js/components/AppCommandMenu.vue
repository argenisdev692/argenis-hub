<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LogOut, Moon, Sun } from '@lucide/vue';
import { useMagicKeys, whenever } from '@vueuse/core';
import { computed } from 'vue';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { useAppearance } from '@/composables/useAppearance';
import { toUrl } from '@/lib/utils';
import { useNavGroups } from '@/modules/app/composables/useNavGroups';
import { logout } from '@/routes';

/**
 * ⌘K / Ctrl+K palette over the same nav config the sidebar renders.
 *
 * Keeps the keyboard path to any destination at one shortcut, and — because it
 * reads `useNavGroups()` — never drifts from the sidebar.
 */
const open = defineModel<boolean>('open', { default: false });

const groups = useNavGroups();
const { resolvedAppearance, updateAppearance } = useAppearance();

/** Only reachable destinations; a disabled sidebar row is not a command. */
const commandGroups = computed(() =>
    groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.comingSoon),
        }))
        .filter((group) => group.items.length > 0),
);

const keys = useMagicKeys();

whenever(keys['Meta+K'], () => {
    open.value = !open.value;
});

whenever(keys['Ctrl+K'], () => {
    open.value = !open.value;
});

function run(action: () => void): void {
    open.value = false;
    action();
}

function go(href: string): void {
    run(() => router.visit(href));
}

function toggleTheme(): void {
    run(() =>
        updateAppearance(
            resolvedAppearance.value === 'dark' ? 'light' : 'dark',
        ),
    );
}
</script>

<template>
    <CommandDialog
        v-model:open="open"
        title="Command palette"
        description="Jump to a page or run a command."
    >
        <CommandInput placeholder="Search pages and commands…" />

        <CommandList>
            <CommandEmpty>No matches.</CommandEmpty>

            <CommandGroup
                v-for="group in commandGroups"
                :key="group.id"
                :heading="group.label"
            >
                <CommandItem
                    v-for="item in group.items"
                    :key="item.title"
                    :value="`${group.label} ${item.title}`"
                    @select="go(toUrl(item.href))"
                >
                    <component :is="item.icon" />
                    {{ item.title }}
                </CommandItem>
            </CommandGroup>

            <CommandSeparator />

            <CommandGroup heading="Commands">
                <CommandItem value="Toggle theme" @select="toggleTheme">
                    <component
                        :is="resolvedAppearance === 'dark' ? Sun : Moon"
                    />
                    Switch to
                    {{ resolvedAppearance === 'dark' ? 'light' : 'dark' }} mode
                </CommandItem>

                <CommandItem
                    value="Log out sign out"
                    @select="
                        run(() =>
                            router.visit(logout().url, { method: 'post' }),
                        )
                    "
                >
                    <LogOut />
                    Log out
                    <CommandShortcut>⇧⌘Q</CommandShortcut>
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>
</template>
