<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CourseUploadForm from '@/modules/course-scripts/components/CourseUploadForm.vue';
import type { CourseUploadLimits } from '@/modules/course-scripts/types';
import { create, index } from '@/routes/course-scripts';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Course scripts', href: index() },
            { title: 'New course', href: create() },
        ],
    },
});

const { limits } = defineProps<{
    limits: CourseUploadLimits;
}>();
</script>

<template>
    <Head title="New course" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">New course</h1>
            <p class="text-sm text-muted-foreground">
                Upload the course index and any source material. Nothing is
                generated until you start a run from the course page.
            </p>
        </header>

        <CourseUploadForm :limits="limits" />
    </div>
</template>
