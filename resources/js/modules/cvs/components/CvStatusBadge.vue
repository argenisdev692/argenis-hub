<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cvStatusPresentation } from '../helpers/cvPresentation';

const { deletedAt = null } = defineProps<{
    /** Non-null means the row is soft-deleted — the only status a CV has. */
    deletedAt?: string | null;
}>();

const presentation = computed(() => cvStatusPresentation(deletedAt));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
