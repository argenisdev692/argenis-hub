<script setup lang="ts">
import { PaperclipIcon, Trash2Icon } from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { ConfirmModal, DataTableRowAction } from '@/common/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCourseContentMutations } from '../composables/useCourseContentMutations';
import {
    DOCUMENT_ICON,
    documentKindLabel,
    formatBytes,
} from '../helpers/coursePresentation';
import type {
    CourseDetail,
    CourseDocument,
    CourseUploadLimits,
} from '../types';
import AttachDocumentSheet from './AttachDocumentSheet.vue';

/**
 * The course's source documents: attach content or style references, remove
 * anything but the index (the index defines the course — the server refuses
 * to detach it, so the UI never offers it).
 */
const { course, limits } = defineProps<{
    course: CourseDetail;
    limits: CourseUploadLimits;
}>();

const { detachDocument } = useCourseContentMutations(() => course.uuid);

const attachOpen = ref(false);
const pendingRemoval = ref<CourseDocument | null>(null);
const confirmRemoveOpen = ref(false);

/** Video numbers by uuid, so a content file reads "Video 3", not a uuid. */
const videoNumbers = computed(
    () => new Map(course.videos.map((video) => [video.uuid, video.number])),
);

function videoLabel(document: CourseDocument): string | null {
    const number = document.video_uuid
        ? videoNumbers.value.get(document.video_uuid)
        : undefined;

    return number !== undefined ? `Video ${number}` : null;
}

function requestRemoval(document: CourseDocument): void {
    pendingRemoval.value = document;
    confirmRemoveOpen.value = true;
}

/** `ConfirmModal` stays busy until this settles; a failure keeps it open (already toasted). */
async function confirmRemoval(): Promise<void> {
    const target = pendingRemoval.value;

    if (!target) {
        return;
    }

    try {
        await detachDocument.mutateAsync(target.uuid);
    } catch {
        return;
    }

    confirmRemoveOpen.value = false;
}
</script>

<template>
    <!-- One root for Vue; the sheet and modal teleport out of it anyway. -->
    <div class="contents">
        <Card>
            <CardHeader class="flex flex-row items-start justify-between gap-2">
                <div class="grid gap-1.5">
                    <CardTitle>Source documents</CardTitle>
                    <CardDescription
                        >What the scripts are grounded in.</CardDescription
                    >
                </div>

                <PermissionGuard permission="UPDATE_COURSE_SCRIPTS">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="attachOpen = true"
                    >
                        <PaperclipIcon class="size-4" aria-hidden="true" />
                        Attach
                    </Button>
                </PermissionGuard>
            </CardHeader>

            <CardContent>
                <ul
                    class="divide-y divide-border rounded-lg border border-border"
                >
                    <li
                        v-for="document in course.documents"
                        :key="document.uuid"
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
                    >
                        <component
                            :is="DOCUMENT_ICON"
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 truncate text-sm">{{
                            document.original_name
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            formatBytes(document.size_bytes)
                        }}</span>
                        <Badge v-if="videoLabel(document)" variant="outline">{{
                            videoLabel(document)
                        }}</Badge>
                        <Badge variant="secondary">{{
                            documentKindLabel(document.kind)
                        }}</Badge>

                        <PermissionGuard
                            v-if="document.kind !== 'index'"
                            permission="UPDATE_COURSE_SCRIPTS"
                        >
                            <DataTableRowAction
                                :icon="Trash2Icon"
                                :label="`Remove ${document.original_name}`"
                                tooltip="Remove"
                                destructive
                                @click="requestRemoval(document)"
                            />
                        </PermissionGuard>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <AttachDocumentSheet
            v-model:open="attachOpen"
            :course-uuid="course.uuid"
            :limits="limits"
        />

        <ConfirmModal
            v-model:open="confirmRemoveOpen"
            destructive
            title="Remove this document?"
            :description="`“${pendingRemoval?.original_name ?? ''}” — scripts already generated keep their content; new generations no longer use it.`"
            confirm-label="Remove"
            @confirm="confirmRemoval"
        />
    </div>
</template>
