<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangleIcon,
    ArrowLeftIcon,
    DownloadIcon,
    FileTextIcon,
    ListChecksIcon,
    RotateCcwIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { ConfirmModal } from '@/common/table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/composables/usePermissions';
import VideoEditCutReviewDialog from '@/modules/video-edits/components/VideoEditCutReviewDialog.vue';
import VideoEditModeBadge from '@/modules/video-edits/components/VideoEditModeBadge.vue';
import VideoEditStatusBadge from '@/modules/video-edits/components/VideoEditStatusBadge.vue';
import { useVideoEdit } from '@/modules/video-edits/composables/useVideoEdit';
import { useVideoEditMutations } from '@/modules/video-edits/composables/useVideoEditMutations';
import {
    cutReasonLabel,
    formatDateTime,
    formatDurationMs,
    isActiveStatus,
    processingStageLabel,
    videoEditReference,
} from '@/modules/video-edits/helpers/videoEditPresentation';
import { index } from '@/routes/video-edits';
import { report } from '@/routes/video-edits/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Video edits', href: index() }],
    },
});

const { uuid } = defineProps<{ uuid: string }>();

const { data: edit, isLoading } = useVideoEdit(() => uuid);
const { retryVideoEdit, deleteVideoEdit, downloadVideoEdit } =
    useVideoEditMutations();

const title = computed(() => `Edit ${videoEditReference(uuid)}`);

const reportPdfUrl = computed(() =>
    report.url(uuid, { query: { format: 'pdf' } }),
);

const summary = computed(() => {
    const current = edit.value?.summary;

    return current
        ? [
              {
                  label: 'Original',
                  value: formatDurationMs(current.original_duration_ms),
              },
              {
                  label: 'Final',
                  value: formatDurationMs(current.final_duration_ms),
              },
              {
                  label: 'Removed',
                  value: formatDurationMs(current.removed_duration_ms),
              },
              {
                  label: 'Cuts applied',
                  value: String(current.applied_cut_count),
              },
          ]
        : [];
});

const timeline = computed(() =>
    [
        {
            label: 'Created',
            value: formatDateTime(edit.value?.created_at ?? null),
        },
        {
            label: 'Queued',
            value: formatDateTime(edit.value?.queued_at ?? null),
        },
        {
            label: 'Started',
            value: formatDateTime(edit.value?.started_at ?? null),
        },
        {
            label: 'Completed',
            value: formatDateTime(edit.value?.completed_at ?? null),
        },
        {
            label: 'Failed',
            value: formatDateTime(edit.value?.failed_at ?? null),
        },
    ].filter((entry): entry is { label: string; value: string } =>
        Boolean(entry.value),
    ),
);

const { can } = usePermissions();

const pendingReview = computed(() =>
    edit.value?.can_review && edit.value.review ? edit.value.review : null,
);

const reviewOpen = ref(false);
let hasAutoOpenedReview = false;

/**
 * The review is the one thing blocking the render, so it opens by itself the
 * first time the edit reaches it. Only once: a closed dialog means "later",
 * and the banner below reopens it.
 */
watch(
    () => pendingReview.value !== null && can('CREATE_VIDEO_EDITS'),
    (shouldOpen) => {
        if (shouldOpen && !hasAutoOpenedReview) {
            hasAutoOpenedReview = true;
            reviewOpen.value = true;
        }
    },
    { immediate: true },
);

const confirmDeleteOpen = ref(false);

async function confirmDelete(): Promise<void> {
    try {
        await deleteVideoEdit.mutateAsync(uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    router.visit(index());
}

async function onRetry(): Promise<void> {
    await retryVideoEdit.mutateAsync(uuid).catch(() => undefined);
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to video edits"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <h1 class="flex-1 text-2xl font-semibold tracking-tight">
                {{ title }}
            </h1>

            <template v-if="edit">
                <PermissionGuard permission="DOWNLOAD_VIDEO_EDITS">
                    <Button
                        v-if="edit.can_download"
                        :disabled="downloadVideoEdit.isLoading.value"
                        @click="downloadVideoEdit.mutate(uuid)"
                    >
                        <DownloadIcon class="size-4" aria-hidden="true" />
                        Download
                    </Button>
                </PermissionGuard>

                <PermissionGuard permission="VIEW_VIDEO_EDITS">
                    <Button
                        v-if="
                            edit.mode === 'ai_edit' &&
                            edit.status === 'completed'
                        "
                        as="a"
                        variant="outline"
                        :href="reportPdfUrl"
                    >
                        <FileTextIcon class="size-4" aria-hidden="true" />
                        AI report (PDF)
                    </Button>
                </PermissionGuard>

                <PermissionGuard permission="RETRY_VIDEO_EDITS">
                    <Button
                        v-if="edit.can_retry"
                        variant="outline"
                        :disabled="retryVideoEdit.isLoading.value"
                        @click="onRetry"
                    >
                        <RotateCcwIcon class="size-4" aria-hidden="true" />
                        Retry
                    </Button>
                </PermissionGuard>

                <PermissionGuard permission="DELETE_VIDEO_EDITS">
                    <Button
                        v-if="edit.can_delete"
                        variant="ghost"
                        size="icon"
                        aria-label="Delete edit"
                        @click="confirmDeleteOpen = true"
                    >
                        <Trash2Icon
                            class="size-4 text-destructive"
                            aria-hidden="true"
                        />
                    </Button>
                </PermissionGuard>
            </template>
        </div>

        <div v-if="isLoading && !edit" class="grid gap-4" aria-busy="true">
            <Skeleton class="h-32 w-full animate-pulse" />
            <Skeleton class="h-48 w-full animate-pulse" />
        </div>

        <template v-else-if="edit">
            <Card>
                <CardHeader>
                    <div class="flex flex-wrap items-center gap-2">
                        <VideoEditModeBadge :mode="edit.mode" />
                        <VideoEditStatusBadge :status="edit.status" />
                        <span class="text-xs text-muted-foreground">
                            Attempt {{ edit.attempts }}
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div
                        v-if="isActiveStatus(edit.status)"
                        class="flex flex-col gap-2"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="flex justify-between text-sm">
                            <span>{{
                                processingStageLabel(edit.current_stage)
                            }}</span>
                            <span class="tabular-nums"
                                >{{ edit.progress_percent }}%</span
                            >
                        </div>
                        <Progress :model-value="edit.progress_percent" />
                    </div>

                    <PermissionGuard
                        v-if="pendingReview"
                        permission="CREATE_VIDEO_EDITS"
                    >
                        <div
                            class="flex flex-col gap-3 rounded-lg border border-primary/40 bg-muted/50 p-3 text-sm sm:flex-row sm:items-center"
                            role="status"
                        >
                            <ListChecksIcon
                                class="hidden size-4 shrink-0 sm:block"
                                aria-hidden="true"
                            />
                            <div class="flex flex-1 flex-col gap-1">
                                <p class="font-medium">
                                    The AI found
                                    {{ pendingReview.cuts.length }}
                                    {{
                                        pendingReview.cuts.length === 1
                                            ? 'passage'
                                            : 'passages'
                                    }}
                                    you may want to remove.
                                </p>
                                <p class="text-muted-foreground">
                                    Nothing is cut or rendered until you review
                                    them.
                                </p>
                            </div>
                            <Button @click="reviewOpen = true">
                                Review cuts
                            </Button>
                        </div>
                    </PermissionGuard>

                    <div
                        v-if="edit.failure"
                        class="flex gap-3 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm"
                        role="alert"
                    >
                        <AlertTriangleIcon
                            class="size-4 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                        <div class="flex flex-col gap-1">
                            <p class="font-medium">
                                {{ edit.failure.message }}
                            </p>
                            <p
                                v-if="edit.retry_available_until"
                                class="text-muted-foreground"
                            >
                                Retry available until
                                {{
                                    formatDateTime(edit.retry_available_until)
                                }}.
                            </p>
                        </div>
                    </div>

                    <ul
                        v-if="edit.warnings.length"
                        class="list-disc pl-5 text-sm text-muted-foreground"
                    >
                        <li v-for="warning in edit.warnings" :key="warning">
                            {{ warning }}
                        </li>
                    </ul>

                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div v-for="item in summary" :key="item.label">
                            <dt class="text-xs text-muted-foreground">
                                {{ item.label }}
                            </dt>
                            <dd class="text-lg font-semibold tabular-nums">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Clips</CardTitle>
                        <CardDescription
                            >In the order they were joined.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <ol class="flex flex-col gap-2 text-sm">
                            <li
                                v-for="source in edit.sources"
                                :key="source.uuid"
                                class="flex items-baseline justify-between gap-3"
                            >
                                <span class="truncate">
                                    {{ source.position }}.
                                    {{ source.original_name }}
                                </span>
                                <span
                                    class="shrink-0 text-muted-foreground tabular-nums"
                                >
                                    {{ formatDurationMs(source.duration_ms) }}
                                    <template v-if="!source.available">
                                        · purged</template
                                    >
                                </span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Timeline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="flex flex-col gap-2 text-sm">
                            <div
                                v-for="entry in timeline"
                                :key="entry.label"
                                class="flex justify-between gap-3"
                            >
                                <dt class="text-muted-foreground">
                                    {{ entry.label }}
                                </dt>
                                <dd class="tabular-nums">{{ entry.value }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="edit.applied_cuts.length">
                <CardHeader>
                    <CardTitle>Applied cuts</CardTitle>
                    <CardDescription>
                        {{ edit.applied_cuts.length }} cuts ·
                        {{ edit.summary.rejected_decision_count }} proposals
                        rejected
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="max-h-96 overflow-auto">
                        <table class="w-full text-sm">
                            <thead
                                class="sticky top-0 bg-card text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th scope="col" class="py-2 text-center">
                                        #
                                    </th>
                                    <th scope="col" class="py-2 text-center">
                                        From
                                    </th>
                                    <th scope="col" class="py-2 text-center">
                                        To
                                    </th>
                                    <th scope="col" class="py-2 text-center">
                                        Length
                                    </th>
                                    <th scope="col" class="py-2 text-left">
                                        Why
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="cut in edit.applied_cuts"
                                    :key="cut.sequence"
                                    class="border-t border-border"
                                >
                                    <td class="py-1.5 text-center tabular-nums">
                                        {{ cut.sequence }}
                                    </td>
                                    <td class="py-1.5 text-center tabular-nums">
                                        {{ formatDurationMs(cut.start_ms) }}
                                    </td>
                                    <td class="py-1.5 text-center tabular-nums">
                                        {{ formatDurationMs(cut.end_ms) }}
                                    </td>
                                    <td class="py-1.5 text-center tabular-nums">
                                        {{ formatDurationMs(cut.duration_ms) }}
                                    </td>
                                    <td class="py-1.5">
                                        {{
                                            cut.reasons
                                                .map(cutReasonLabel)
                                                .join(', ')
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>

    <VideoEditCutReviewDialog
        v-if="pendingReview"
        v-model:open="reviewOpen"
        :uuid="uuid"
        :review="pendingReview"
        :expires-at="edit?.review_expires_at ?? null"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this edit permanently?"
        description="The result video, any retained clips and every record of the edit are removed. This cannot be undone."
        confirm-label="Delete"
        @confirm="confirmDelete"
    />
</template>
