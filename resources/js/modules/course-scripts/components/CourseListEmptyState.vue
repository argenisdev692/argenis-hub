<script setup lang="ts">
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Button } from '@/components/ui/button';

/**
 * The list's empty state for both layouts: a reset shortcut when filters
 * narrowed the list to nothing, the upload prompt otherwise.
 */
const { filtered } = defineProps<{
    filtered: boolean;
}>();

const emit = defineEmits<{
    clear: [];
}>();
</script>

<template>
    <EmptyState
        v-if="filtered"
        title="No courses match these filters"
        description="Try widening the date range or clearing the search."
    >
        <template #action>
            <Button variant="outline" size="sm" @click="emit('clear')">
                Clear filters
            </Button>
        </template>
    </EmptyState>
    <EmptyState
        v-else
        title="No courses found"
        description="Upload a course index to start generating video scripts."
    />
</template>
