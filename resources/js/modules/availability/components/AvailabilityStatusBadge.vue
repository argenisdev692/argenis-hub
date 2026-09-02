<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { availabilityStatusPresentation } from '../helpers/availabilityPresentation';

/**
 * Soft-delete state, shared by both entities: neither a weekly rule nor a date
 * exception has a lifecycle column, so `deleted_at` is the whole of its status.
 */
const { deletedAt = null } = defineProps<{
    deletedAt?: string | null;
}>();

const presentation = computed(() => availabilityStatusPresentation(deletedAt));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
