<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { useTimeoutFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';

const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

const fullName = computed(() =>
    [user.value.first_name, user.value.last_name].filter(Boolean).join(' '),
);

/**
 * Hover intent. The menu opens on pointer enter and closes on a short delay so
 * the cursor can cross the gap between the trigger and the floating panel
 * without it snapping shut. Click and keyboard still drive the same controlled
 * state, and touch devices never fire these pointer events at all.
 */
const isOpen = ref(false);
const wasOpenedByHover = ref(false);

const { start: scheduleClose, stop: cancelClose } = useTimeoutFn(
    () => {
        isOpen.value = false;
    },
    180,
    { immediate: false },
);

function openMenu(): void {
    cancelClose();
    wasOpenedByHover.value = true;
    isOpen.value = true;
}

/** Reka's own toggling — trigger click, Escape, outside click, item select. */
function setOpen(open: boolean): void {
    cancelClose();
    isOpen.value = open;
}

/** A real click or key press supersedes hover, so focus behaves normally. */
function markDeliberateInteraction(): void {
    wasOpenedByHover.value = false;
}

/**
 * Merely passing the cursor over the avatar must not steal focus from whatever
 * the user was doing, so the panel's focus handoff is suppressed while the menu
 * is only hover-open. Click and keyboard openings keep the default behaviour.
 */
function onOpenAutoFocus(event: Event): void {
    if (wasOpenedByHover.value) {
        event.preventDefault();
    }
}

function onCloseAutoFocus(event: Event): void {
    if (wasOpenedByHover.value) {
        event.preventDefault();
    }

    wasOpenedByHover.value = false;
}
</script>

<template>
    <DropdownMenu :open="isOpen" :modal="false" @update:open="setOpen">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                class="h-9 gap-2 rounded-full border border-transparent px-1 transition-colors duration-200 ease-brand hover:border-glass-border hover:bg-surface-glass-strong data-[state=open]:border-glass-border data-[state=open]:bg-surface-glass-strong sm:pr-2.5"
                :aria-label="`Account menu — ${fullName}`"
                data-test="user-menu-button"
                @mouseenter="openMenu"
                @mouseleave="scheduleClose()"
                @pointerdown="markDeliberateInteraction"
                @keydown="markDeliberateInteraction"
            >
                <Avatar class="size-7 overflow-hidden rounded-full">
                    <AvatarImage
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="fullName"
                    />
                    <AvatarFallback
                        class="bg-brand-gradient text-xs font-semibold text-white"
                    >
                        {{ getInitials(fullName) }}
                    </AvatarFallback>
                </Avatar>

                <span
                    class="hidden max-w-32 truncate text-sm font-medium sm:inline"
                >
                    {{ fullName }}
                </span>
                <ChevronDown
                    class="hidden size-3.5 text-muted-foreground sm:inline"
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="8"
            class="w-60"
            @mouseenter="openMenu"
            @mouseleave="scheduleClose()"
            @open-auto-focus="onOpenAutoFocus"
            @close-auto-focus="onCloseAutoFocus"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
