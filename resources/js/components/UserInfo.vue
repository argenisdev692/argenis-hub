<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const { user, showEmail = false } = defineProps<Props>();

const { getInitials } = useInitials();

const fullName = computed(() =>
    [user.first_name, user.last_name].filter(Boolean).join(' '),
);

// Compute whether we should show the avatar image
const showAvatar = computed(() => user.avatar && user.avatar !== '');
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="fullName" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(fullName) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ fullName }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
