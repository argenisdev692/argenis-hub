<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import {
    postingStageLabel,
    postingStageTone,
} from '../helpers/studioPresentation';

const { status, deletedAt = null } = defineProps<{
    status: string;
    deletedAt?: string | null;
}>();

const isSuspended = computed(() => deletedAt !== null);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                isSuspended
                    ? 'bg-destructive/10 text-destructive'
                    : postingStageTone(status),
            )
        "
    >
        {{ isSuspended ? 'Suspended' : postingStageLabel(status) }}
    </span>
</template>
