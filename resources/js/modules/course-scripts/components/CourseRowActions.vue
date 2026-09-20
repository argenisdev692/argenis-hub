<script setup lang="ts">
import { EyeIcon, RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { DataTableRowAction } from '@/common/table';
import { show } from '@/routes/course-scripts';
import type { CourseListItem } from '../types';

/**
 * A course row's actions, shared by the desktop table and the phone cards so
 * both layouts always offer the same actions behind the same permissions.
 *
 * No Edit: a course has no single edit form — its notes, bible and video
 * briefs are edited in place on the course page. No View on a deleted row:
 * `show` resolves live courses only.
 */
const { course } = defineProps<{
    course: CourseListItem;
}>();

const emit = defineEmits<{
    delete: [course: CourseListItem];
    restore: [course: CourseListItem];
}>();
</script>

<template>
    <!-- `contents`: one root for Vue, zero boxes for the parent's flex row. -->
    <div class="contents">
        <template v-if="course.deleted_at === null">
            <PermissionGuard permission="VIEW_COURSE_SCRIPTS">
                <DataTableRowAction
                    :icon="EyeIcon"
                    :label="`View ${course.title}`"
                    tooltip="View"
                    :href="show.url(course.uuid)"
                    prefetch
                />
            </PermissionGuard>

            <PermissionGuard permission="DELETE_COURSE_SCRIPTS">
                <DataTableRowAction
                    :icon="Trash2Icon"
                    :label="`Delete ${course.title}`"
                    tooltip="Delete"
                    destructive
                    @click="emit('delete', course)"
                />
            </PermissionGuard>
        </template>

        <PermissionGuard v-else permission="RESTORE_COURSE_SCRIPTS">
            <DataTableRowAction
                :icon="RotateCcwIcon"
                :label="`Restore ${course.title}`"
                tooltip="Restore"
                @click="emit('restore', course)"
            />
        </PermissionGuard>
    </div>
</template>
