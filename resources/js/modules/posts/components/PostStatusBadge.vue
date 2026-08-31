<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { postStatusPresentation } from '../helpers/postPresentation';

const { status, deletedAt = null } = defineProps<{
    status: string;
    /** Non-null wins over `status` — a suspended post is suspended, first. */
    deletedAt?: string | null;
}>();

const presentation = computed(() => postStatusPresentation(status, deletedAt));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
