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
import {
    dayOfWeekLabel,
    formatDateTime,
    formatTimeRange,
} from '@/modules/availability/helpers/availabilityPresentation';
import type { AvailabilityRuleDetail } from '@/modules/availability/types';
import { index } from '@/routes/availability-rules';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Availability rules', href: index() }],
    },
});

const { availabilityRule } = defineProps<{
    availabilityRule: AvailabilityRuleDetail;
}>();

const title = computed(() => dayOfWeekLabel(availabilityRule.day_of_week));

const hours = computed(
    () =>
        formatTimeRange(
            availabilityRule.start_time,
            availabilityRule.end_time,
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
            value: formatDateTime(availabilityRule.created_at),
        },
        {
            label: 'Last updated',
            value: formatDateTime(availabilityRule.updated_at),
        },
        {
            label: 'Suspended',
            value: formatDateTime(availabilityRule.deleted_at),
        },
    ].filter((entry): entry is { label: string; value: string } =>
        Boolean(entry.value),
    ),
);
</script>

<template>
    <Head :title="`${title} availability`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to availability rules"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <div class="flex flex-1 flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p class="text-sm text-muted-foreground">{{ hours }}</p>
            </div>

            <AvailabilityStatusBadge
                :deleted-at="availabilityRule.deleted_at"
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Weekly slot</CardTitle>
                <CardDescription>
                    Repeats every {{ title }}. National holidays and date
                    exceptions override it for a specific date.
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Weekday</dt>
                        <dd class="text-sm">{{ title }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-muted-foreground">Hours</dt>
                        <dd class="text-sm tabular-nums">{{ hours }}</dd>
                    </div>

                    <div class="flex flex-col gap-1">
                        <dt class="text-xs text-muted-foreground">Slot</dt>
                        <dd>
                            <AvailabilityOpenBadge
                                :is-available="availabilityRule.is_available"
                                wording="available"
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
