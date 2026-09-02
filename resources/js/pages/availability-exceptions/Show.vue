<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AvailabilityOpenBadge from '@/modules/availability/components/AvailabilityOpenBadge.vue';
import AvailabilityStatusBadge from '@/modules/availability/components/AvailabilityStatusBadge.vue';
import ExceptionSourceBadge from '@/modules/availability/components/ExceptionSourceBadge.vue';
import {
    formatDateOnly,
    formatDateTime,
    formatTimeRange,
} from '@/modules/availability/helpers/availabilityPresentation';
import type { AvailabilityExceptionDetail } from '@/modules/availability/types';
import { index } from '@/routes/availability-exceptions';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Date exceptions', href: index() }],
    },
});

const { availabilityException } = defineProps<{
    availabilityException: AvailabilityExceptionDetail;
}>();

const title = computed(
    () =>
        formatDateOnly(availabilityException.date) ??
        availabilityException.date,
);

/** Null on a closure, which is the normal case — hence the em dash fallback. */
const hours = computed(
    () =>
        formatTimeRange(
            availabilityException.start_time,
            availabilityException.end_time,
        ) ?? '—',
);

/**
 * Assembled rather than rendered as a fixed table: a row that has never been
 * edited has `updated_at === created_at`, and one that is active has no
 * suspension date. Empty entries are dropped instead of showing an em dash on
 * every line a healthy row does not have.
 */
const timeline = computed(() =>
    [
        {
            label: 'Created',
            value: formatDateTime(availabilityException.created_at),
        },
        {
            label: 'Last updated',
            value: formatDateTime(availabilityException.updated_at),
        },
        {
            label: 'Suspended',
            value: formatDateTime(availabilityException.deleted_at),
        },
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
                aria-label="Back to date exceptions"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <div class="flex flex-1 flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p
                    class="text-sm"
                    :class="
                        availabilityException.reason
                            ? 'text-muted-foreground'
                            : 'text-muted-foreground/70'
                    "
                >
                    {{ availabilityException.reason || 'No reason recorded.' }}
                </p>
            </div>

            <AvailabilityStatusBadge
                :deleted-at="availabilityException.deleted_at"
            />
        </div>

        <!--
            A holiday row is rebuilt by `HolidayMaterializer` on the yearly sync
            and on a country change, so an edit to one does not survive. Stated
            on the detail page too, not only in the dialog — this is where an
            operator lands from a link before deciding to change anything.
        -->
        <p
            v-if="availabilityException.source === 'holiday'"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            Materialised from the national holiday calendar. Edits are
            overwritten the next time holidays are synced.
        </p>

        <Card>
            <CardHeader>
                <CardTitle>Override</CardTitle>
                <CardDescription>
                    What this date does instead of following the weekly
                    template.
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Date</dt>
                        <dd class="text-sm">{{ title }}</dd>
                    </div>

                    <div class="flex flex-col gap-1">
                        <dt class="text-xs text-muted-foreground">Day</dt>
                        <dd>
                            <AvailabilityOpenBadge
                                :is-available="
                                    availabilityException.is_available
                                "
                            />
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-muted-foreground">Hours</dt>
                        <dd class="text-sm tabular-nums">{{ hours }}</dd>
                    </div>

                    <div class="flex flex-col gap-1">
                        <dt class="text-xs text-muted-foreground">Source</dt>
                        <dd>
                            <ExceptionSourceBadge
                                :source="availabilityException.source"
                            />
                        </dd>
                    </div>
                </dl>

                <dl class="grid gap-3 sm:grid-cols-2">
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
