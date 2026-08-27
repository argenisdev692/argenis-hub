<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ActivityLogPropertiesCard from '@/modules/activity-log/components/ActivityLogPropertiesCard.vue';
import { activityLogEventPresentation } from '@/modules/activity-log/helpers/activityLogEvent';
import { formatActivityAbsolute } from '@/modules/activity-log/helpers/formatActivityTimestamp';
import type { ActivityLogDetail } from '@/modules/activity-log/types';
import { index } from '@/routes/activity-logs';

const { log } = defineProps<{ log: ActivityLogDetail }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Activity log', href: index() },
            { title: 'Entry', href: index() },
        ],
    },
});

const event = computed(() => activityLogEventPresentation(log.event));

type SummaryRow = { label: string; value: string };

const summary = computed<SummaryRow[]>(() => [
    { label: 'Log name', value: log.log_name ?? '—' },
    { label: 'Event', value: event.value.label },
    { label: 'Actor', value: log.causer_label ?? 'System' },
    {
        label: 'Actor type',
        value:
            log.causer_type && log.causer_id
                ? `${log.causer_type} #${log.causer_id}`
                : (log.causer_type ?? '—'),
    },
    {
        label: 'Subject',
        value:
            log.subject_type && log.subject_id
                ? `${log.subject_type} #${log.subject_id}`
                : (log.subject_type ?? '—'),
    },
    { label: 'Recorded', value: formatActivityAbsolute(log.created_at) },
    { label: 'Updated', value: formatActivityAbsolute(log.updated_at) },
]);
</script>

<template>
    <Head :title="`Activity — ${log.description}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <Button as-child variant="ghost" size="sm" class="-ml-2 w-fit">
            <Link :href="index()">
                <ArrowLeftIcon class="size-4" aria-hidden="true" />
                Back to activity log
            </Link>
        </Button>

        <header class="flex flex-col gap-2">
            <div class="flex items-center gap-3">
                <Badge :variant="event.variant">{{ event.label }}</Badge>
                <span class="text-sm text-muted-foreground tabular-nums">
                    #{{ log.id }}
                </span>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ log.description }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ log.causer_label ?? 'System' }} ·
                {{ formatActivityAbsolute(log.created_at) }}
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Summary</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-3 sm:grid-cols-[12rem_1fr]">
                    <template v-for="row in summary" :key="row.label">
                        <dt class="text-sm text-muted-foreground">
                            {{ row.label }}
                        </dt>
                        <dd class="text-sm break-words">{{ row.value }}</dd>
                    </template>
                </dl>
            </CardContent>
        </Card>

        <ActivityLogPropertiesCard title="Properties" :data="log.properties" />

        <ActivityLogPropertiesCard
            title="Attribute changes"
            :data="log.attribute_changes"
        />
    </div>
</template>
