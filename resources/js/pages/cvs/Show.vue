<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, DownloadIcon, StarIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import CvNicheBadge from '@/modules/cvs/components/CvNicheBadge.vue';
import CvStatusBadge from '@/modules/cvs/components/CvStatusBadge.vue';
import {
    cvFileTypePresentation,
    cvLabel,
    cvOwnerName,
    formatDateTime,
} from '@/modules/cvs/helpers/cvPresentation';
import type { Cv } from '@/modules/cvs/types';
import { index } from '@/routes/cvs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'CVs', href: index() }],
    },
});

const { cv } = defineProps<{
    cv: Cv;
}>();

const title = computed(() => cvLabel(cv));

const fileType = computed(() => cvFileTypePresentation(cv.file_type));

/**
 * Assembled rather than rendered as a fixed table: a row that has never been
 * edited has `updated_at === created_at`, and one that is active has no
 * suspension date. Empty entries are dropped instead of showing an em dash on
 * every line a healthy row does not have.
 */
const timeline = computed(() =>
    [
        { label: 'Uploaded', value: formatDateTime(cv.created_at) },
        { label: 'Last updated', value: formatDateTime(cv.updated_at) },
        { label: 'Suspended', value: formatDateTime(cv.deleted_at) },
    ].filter((entry): entry is { label: string; value: string } =>
        Boolean(entry.value),
    ),
);
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to CVs"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <div class="flex flex-1 items-center gap-2">
                <StarIcon
                    v-if="cv.is_primary"
                    class="size-5 shrink-0 fill-current text-primary"
                    aria-hidden="true"
                />
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
            </div>

            <CvStatusBadge :deleted-at="cv.deleted_at" />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
                <CardDescription>
                    Stored privately. The extracted résumé text is never sent to
                    the browser.
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <div
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-muted/40 p-3"
                >
                    <component
                        :is="fileType.icon"
                        class="size-8 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />

                    <div class="flex min-w-0 flex-1 flex-col">
                        <p class="truncate text-sm font-medium">
                            {{ cv.original_filename }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ fileType.label }}
                        </p>
                    </div>

                    <!--
                        A plain link, not an XHR: `download_url` is a pre-signed
                        R2 URL valid for 15 minutes, so the browser fetches the
                        object straight from storage and this app never proxies
                        a résumé through its own memory. Absent — and the button
                        with it — when R2 could not sign one.
                    -->
                    <Button
                        v-if="cv.download_url"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <a
                            :href="cv.download_url"
                            rel="noopener noreferrer"
                            target="_blank"
                        >
                            <DownloadIcon class="size-4" aria-hidden="true" />
                            Download
                        </a>
                    </Button>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Niche</dt>
                        <dd class="mt-1">
                            <CvNicheBadge :niche="cv.niche" />
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-muted-foreground">Owner</dt>
                        <dd class="text-sm">{{ cvOwnerName(cv) }}</dd>
                    </div>

                    <div v-for="entry in timeline" :key="entry.label">
                        <dt class="text-xs text-muted-foreground">
                            {{ entry.label }}
                        </dt>
                        <dd class="text-sm">{{ entry.value }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
