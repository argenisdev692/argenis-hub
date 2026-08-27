<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
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
</script>

<template>
    <!--
      Click (or keyboard) opens the menu — never hover. Reka's own uncontrolled
      state drives it: trigger click toggles, Escape / outside click / item
      select close it.
    -->
    <DropdownMenu :modal="false">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                class="h-9 gap-2 rounded-full border border-transparent px-1 transition-colors duration-200 ease-brand hover:border-glass-border hover:bg-surface-glass-strong data-[state=open]:border-glass-border data-[state=open]:bg-surface-glass-strong sm:pr-2.5"
                :aria-label="`Account menu — ${fullName}`"
                data-test="user-menu-button"
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

        <DropdownMenuContent align="end" :side-offset="8" class="w-60">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
