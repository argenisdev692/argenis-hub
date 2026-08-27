<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { EyeIcon } from '@lucide/vue';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import type { FilterSelectOption } from '@/common/form';
import { FilterSelect } from '@/common/form';
import type {
    DataTableColumn,
    DataTableSort,
    DateRange,
    PaginationMeta,
} from '@/common/table';
import {
    DataTable,
    DataTableDateRangeFilter,
    DataTableExportMenu,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import {
    defaultActivityLogFilters,
    useActivityLogs,
} from '@/modules/activity-log/composables/useActivityLogs';
import { activityLogEventPresentation } from '@/modules/activity-log/helpers/activityLogEvent';
import { formatActivityAbsolute } from '@/modules/activity-log/helpers/formatActivityTimestamp';
import type {
    ActivityLogFilters,
    ActivityLogRow,
} from '@/modules/activity-log/types';
import { exportMethod, index, show } from '@/routes/activity-logs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Activity log', href: index() }],
    },
});

const { rows, meta: queryMeta, filters, isLoading } = useActivityLogs();

// Restore search / event / date range / page from the URL on load and mirror
// every later change back with `history.replaceState`. `per_page` and
// `sort_direction` are fixed here, so they stay out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultActivityLogFilters(),
    exclude: ['per_page', 'sort_direction'],
});

const meta = computed<PaginationMeta>(
    () =>
        queryMeta.value ?? {
            current_page: 1,
            last_page: 1,
            per_page: filters.value.per_page,
            from: null,
            to: null,
            total: 0,
        },
);

const searchTerm = computed<string>({
    get: () => filters.value.search,
    set: (value) => {
        filters.value.search = value;
        filters.value.page = 1;
    },
});

const dateRange = computed<DateRange>({
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) => {
        filters.value.date_from = value.from;
        filters.value.date_to = value.to;
        filters.value.page = 1;
    },
});

const EVENT_OPTIONS: FilterSelectOption[] = [
    { value: 'all', label: 'All events' },
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'restored', label: 'Restored' },
];

const eventValue = computed<string>(() => filters.value.event || 'all');

function onEventChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    const next = typeof value === 'string' ? value : 'all';
    filters.value.event = (
        next === 'all' ? '' : next
    ) as ActivityLogFilters['event'];
    filters.value.page = 1;
}

/** The active filter set, as the export endpoint's query string wants it. */
const exportParams = computed(() => ({
    search: filters.value.search || undefined,
    event: filters.value.event || undefined,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
    sort_direction: filters.value.sort_direction,
}));

const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<ActivityLogRow>[] = [
    {
        key: 'created_at',
        header: 'When',
        value: (row) => formatActivityAbsolute(row.created_at),
        sortable: true,
        align: 'left',
    },
    {
        key: 'causer_label',
        header: 'Actor',
        value: (row) => row.causer_label ?? 'System',
        align: 'left',
    },
    { key: 'event', header: 'Event' },
    {
        key: 'description',
        header: 'Description',
        value: (row) => row.description,
        align: 'left',
    },
    { key: 'subject', header: 'Subject', hideOnMobile: true },
    {
        key: 'log_name',
        header: 'Log',
        value: (row) => row.log_name,
        hideOnMobile: true,
    },
];

/**
 * The trail sorts on `created_at` only, and by direction alone — so the sort
 * model just translates that one column's direction to `sort_direction`.
 */
const sortModel = computed<DataTableSort | null>({
    get: () => ({
        field: 'created_at',
        direction: filters.value.sort_direction,
    }),
    set: (value) => {
        if (!value) {
            return;
        }

        filters.value.sort_direction = value.direction;
        filters.value.page = 1;
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});
</script>

<template>
    <Head title="Activity log" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Activity log</h1>
            <p class="text-sm text-muted-foreground">
                Immutable audit trail across every module. Read-only — entries
                are written by the app and trimmed by a scheduled task.
            </p>
        </header>

        <DataTableToolbar>
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search description, event, subject…"
                    aria-label="Search activity log"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Any date"
                />

                <FilterSelect
                    class="w-40"
                    :options="EVENT_OPTIONS"
                    :clearable="false"
                    :model-value="eventValue"
                    @update:model-value="onEventChange"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_ACTIVITY_LOGS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['csv', 'xlsx', 'pdf']"
                    />
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <p
                class="px-1 pb-2 text-sm text-muted-foreground tabular-nums"
                role="status"
                aria-live="polite"
            >
                {{ recordCounter }}
            </p>

            <DataTable
                v-model:sort="sortModel"
                :rows="rows"
                :columns="columns"
                :loading="isLoading"
                caption="Audit trail entries"
                empty-title="No activity recorded"
                empty-description="Actions across the app will appear here as they happen."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:event`]="{ row }">
                    <Badge
                        :variant="
                            activityLogEventPresentation(row.event).variant
                        "
                    >
                        {{ activityLogEventPresentation(row.event).label }}
                    </Badge>
                </template>

                <template #[`cell:subject`]="{ row }">
                    <span v-if="row.subject_type" class="text-sm">
                        {{ row.subject_type
                        }}<span
                            v-if="row.subject_id"
                            class="text-muted-foreground"
                        >
                            #{{ row.subject_id }}</span
                        >
                    </span>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_ACTIVITY_LOGS">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="View entry"
                        >
                            <Link :href="show(row.id)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>
                </template>
            </DataTable>

            <Paginator
                v-model:page="page"
                :meta="meta"
                :disabled="isLoading"
                label="records"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>
</template>
