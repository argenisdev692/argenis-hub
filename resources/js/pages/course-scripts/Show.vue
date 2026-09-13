<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    DownloadIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { useQueryCache } from '@pinia/colada';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { ConfirmModal } from '@/common/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import CourseStatusBadge from '@/modules/course-scripts/components/CourseStatusBadge.vue';
import { COURSES_KEY } from '@/modules/course-scripts/composables/useCourses';
import {
    DOCUMENT_ICON,
    documentKindLabel,
    formatBytes,
    formatDate,
    generatedProgress,
    pluralize,
    scriptStatusPresentation,
} from '@/modules/course-scripts/helpers/coursePresentation';
import type { CourseDetail, CourseVideo } from '@/modules/course-scripts/types';
import { bundle, destroy, index } from '@/routes/course-scripts';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Course scripts', href: index() }],
    },
});

const { course } = defineProps<{
    course: CourseDetail;
}>();

const queryCache = useQueryCache();

const generatedCount = computed(
    () =>
        course.videos.filter((video) => video.script_status === 'generated')
            .length,
);

/**
 * Videos grouped under their block, in index order. Videos without a block
 * (flat indexes) land in one untitled group so nothing is dropped.
 */
const sections = computed(() => {
    const byBlock = new Map<string | null, CourseVideo[]>();

    for (const video of course.videos) {
        byBlock.set(video.block_uuid, [
            ...(byBlock.get(video.block_uuid) ?? []),
            video,
        ]);
    }

    const titled = course.blocks
        .filter((block) => byBlock.has(block.uuid))
        .map((block) => ({
            key: block.uuid,
            title: `Block ${block.number} · ${block.title}`,
            videos: byBlock.get(block.uuid) ?? [],
        }));

    const unassigned = byBlock.get(null);

    return unassigned
        ? [
              ...titled,
              { key: 'unassigned', title: 'Videos', videos: unassigned },
          ]
        : titled;
});

const facts = computed(() => [
    { label: 'Language', value: course.language.toUpperCase() },
    {
        label: 'Scripts generated',
        value: generatedProgress(generatedCount.value, course.videos.length),
    },
    {
        label: 'Default video length',
        value: `${course.default_video_minutes} min`,
    },
    { label: 'Prepared', value: course.prepared ? 'Yes' : 'Not yet' },
    { label: 'Created', value: formatDate(course.created_at) ?? '—' },
]);

const confirmDeleteOpen = ref(false);

/**
 * An Inertia visit, not an XHR: `destroy()` redirects to the list, which is
 * exactly where the user should land. The list cache is invalidated so the
 * deleted course is gone from it on arrival.
 */
function confirmDelete(): Promise<void> {
    return new Promise((resolve) => {
        router.delete(destroy.url(course.uuid), {
            onSuccess: () => {
                toast.success('Course deleted.');
                queryCache.invalidateQueries({ key: COURSES_KEY });
            },
            onError: () => toast.error('Failed to delete the course.'),
            onFinish: () => resolve(),
        });
    });
}
</script>

<template>
    <Head :title="course.title" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to course scripts"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <h1
                class="min-w-0 flex-1 truncate text-2xl font-semibold tracking-tight"
            >
                {{ course.title }}
            </h1>

            <CourseStatusBadge :status="course.status" />

            <div class="flex w-full gap-2 sm:w-auto">
                <PermissionGuard permission="DOWNLOAD_COURSE_SCRIPTS">
                    <Button
                        v-if="generatedCount > 0"
                        as-child
                        variant="outline"
                    >
                        <!-- A file download, so a plain anchor rather than an Inertia visit. -->
                        <a :href="bundle.url(course.uuid)">
                            <DownloadIcon class="size-4" aria-hidden="true" />
                            Download bundle
                        </a>
                    </Button>
                </PermissionGuard>

                <PermissionGuard permission="DELETE_COURSE_SCRIPTS">
                    <Button
                        variant="destructive"
                        @click="confirmDeleteOpen = true"
                    >
                        <Trash2Icon class="size-4" aria-hidden="true" />
                        Delete
                    </Button>
                </PermissionGuard>
            </div>
        </div>

        <div
            v-if="course.videos_needing_review > 0"
            role="status"
            class="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <TriangleAlertIcon
                class="size-4 shrink-0 text-destructive"
                aria-hidden="true"
            />
            {{
                pluralize(
                    course.videos_needing_review,
                    'video needs',
                    'videos need',
                )
            }}
            review — the index left their brief incomplete.
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Overview</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <div v-for="fact in facts" :key="fact.label">
                        <dt class="text-xs text-muted-foreground">
                            {{ fact.label }}
                        </dt>
                        <dd class="text-sm font-medium tabular-nums">
                            {{ fact.value }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Videos</CardTitle>
                <CardDescription>
                    {{ pluralize(course.videos.length, 'video', 'videos') }}
                    parsed from the index.
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <p
                    v-if="sections.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No videos were found in the index.
                </p>

                <section
                    v-for="section in sections"
                    :key="section.key"
                    class="flex flex-col gap-2"
                >
                    <h2 class="text-sm font-semibold">{{ section.title }}</h2>

                    <ul
                        class="divide-y divide-border rounded-lg border border-border"
                    >
                        <li
                            v-for="video in section.videos"
                            :key="video.uuid"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
                        >
                            <span
                                class="w-8 shrink-0 text-sm text-muted-foreground tabular-nums"
                            >
                                {{ video.number }}
                            </span>
                            <span
                                class="min-w-0 flex-1 truncate text-sm font-medium"
                            >
                                {{ video.title }}
                            </span>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ video.effective_duration_minutes }} min
                            </span>
                            <Badge v-if="video.needs_review" variant="outline">
                                Needs review
                            </Badge>
                            <Badge
                                :variant="
                                    scriptStatusPresentation(
                                        video.script_status,
                                    ).variant
                                "
                            >
                                <component
                                    :is="
                                        scriptStatusPresentation(
                                            video.script_status,
                                        ).icon
                                    "
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{
                                    scriptStatusPresentation(
                                        video.script_status,
                                    ).label
                                }}
                            </Badge>
                        </li>
                    </ul>
                </section>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Source documents</CardTitle>
            </CardHeader>

            <CardContent>
                <ul
                    class="divide-y divide-border rounded-lg border border-border"
                >
                    <li
                        v-for="document in course.documents"
                        :key="document.uuid"
                        class="flex items-center gap-3 px-3 py-2"
                    >
                        <component
                            :is="DOCUMENT_ICON"
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 truncate text-sm">
                            {{ document.original_name }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ formatBytes(document.size_bytes) }}
                        </span>
                        <Badge variant="secondary">
                            {{ documentKindLabel(document.kind) }}
                        </Badge>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this course?"
        description="It moves to the Deleted view of the list and can be restored later."
        confirm-label="Delete"
        @confirm="confirmDelete"
    />
</template>
