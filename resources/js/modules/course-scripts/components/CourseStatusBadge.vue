<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { courseStatusPresentation } from '../helpers/coursePresentation';
import type { CourseStatus } from '../types';

const { status, deletedAt = null } = defineProps<{
    status: CourseStatus;
    /** Non-null overrides the generation status with "Deleted". */
    deletedAt?: string | null;
}>();

const presentation = computed(() =>
    courseStatusPresentation(status, deletedAt),
);
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
