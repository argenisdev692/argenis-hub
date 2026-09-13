<script setup lang="ts">
import type { CourseListItem } from '../types';

/**
 * The courses a confirmation acts on, by title — so the user confirms the rows
 * they picked, not a bare count. Scrolls past a handful of rows.
 */
const { courses } = defineProps<{
    courses: readonly Pick<CourseListItem, 'uuid' | 'title'>[];
}>();
</script>

<template>
    <ul
        class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
    >
        <li
            v-for="course in courses"
            :key="course.uuid"
            class="truncate font-medium"
        >
            {{ course.title }}
        </li>
    </ul>
</template>
