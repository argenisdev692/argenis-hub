<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { DownloadIcon, EyeIcon, PlusIcon, Trash2Icon } from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import type { FilterSelectOption, FilterSelectValue } from '@/common/form';
import { FilterSelect } from '@/common/form';
import type {
    DataTableColumn,
    DataTableSort,
    DateRange,
    PaginationMeta,
} from '@/common/table';
import {
    ConfirmModal,
    DataTable,
    DataTableDateRangeFilter,
    DataTableExportMenu,
    DataTableRowAction,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import VideoEditFormDialog from '@/modules/video-edits/components/VideoEditFormDialog.vue';
import VideoEditModeBadge from '@/modules/video-edits/components/VideoEditModeBadge.vue';
import VideoEditStatusBadge from '@/modules/video-edits/components/VideoEditStatusBadge.vue';
import { useVideoEditMutations } from '@/modules/video-edits/composables/useVideoEditMutations';
import {
    defaultVideoEditFilters,
    useVideoEdits,
} from '@/modules/video-edits/composables/useVideoEdits';
import { buildVideoEditQueryParams } from '@/modules/video-edits/helpers/buildVideoEditQueryParams';
import {
    LISTED_STATUSES,
    VIDEO_EDIT_MODES,
    formatDateShort,
    formatDurationMs,
    videoEditModePresentation,
    videoEditReference,
    videoEditStatusPresentation,
} from '@/modules/video-edits/helpers/videoEditPresentation';
import type {
    VideoEditFilters,
    VideoEditListItem,
    VideoEditSortField,
} from '@/modules/video-edits/types';
import { index, show } from '@/routes/video-edits';
import { exportMethod } from '@/routes/video-edits/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Video edits', href: index() }],
    },
});

const {
    videoEdits,
    meta: queryMeta,
    filters,
    isPending,
    isPlaceholderData,
} = useVideoEdits();

/**
 * First load or a page/filter change still showing the previous rows. NOT
 * `isLoading`: the 4 s progress poll would dim the table and lock its row
 * actions and paginator on every tick while an edit renders.
 */
const isTableBusy = computed(() => isPending.value || isPlaceholderData.value);
const { deleteVideoEdit, bulkDeleteVideoEdits, downloadVideoEdit } =
    useVideoEditMutations();

useUrlSyncedFilters(filters, {
    defaults: defaultVideoEditFilters(),
    exclude: ['per_page'],
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

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});

/** Changing any filter resets the page and drops a selection it may hide. */
function setFilter<K extends keyof VideoEditFilters>(
    key: K,
    value: VideoEditFilters[K],
): void {
    filters.value[key] = value;
    filters.value.page = 1;
    selection.value = [];
}

const searchTerm = computed<string>({
    get: () => filters.value.search,
    set: (value) => setFilter('search', value),
});

const dateRange = computed<DateRange>({
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) => {
        filters.value.date_to = value.to;
        setFilter('date_from', value.from);
    },
});

const statusOptions: FilterSelectOption[] = LISTED_STATUSES.map((status) => ({
    value: status,
    label: videoEditStatusPresentation(status).label,
}));

const modeOptions: FilterSelectOption[] = VIDEO_EDIT_MODES.map((mode) => ({
    value: mode,
    label: videoEditModePresentation(mode).label,
}));

function onStatusChange(
    value: FilterSelectValue | FilterSelectValue[] | null,
): void {
    setFilter(
        'status',
        LISTED_STATUSES.find((status) => status === value) ?? '',
    );
}

function onModeChange(
    value: FilterSelectValue | FilterSelectValue[] | null,
): void {
    setFilter('mode', VIDEO_EDIT_MODES.find((mode) => mode === value) ?? '');
}

/** Same helper as the list query — the export always matches the table. */
const exportParams = computed(() => buildVideoEditQueryParams(filters.value));
const exportEndpoint = exportMethod.url();

const SORTABLE: readonly VideoEditSortField[] = [
    'created_at',
    'completed_at',
    'status',
    'mode',
    'final_duration_ms',
    'applied_cut_count',
];

const columns: DataTableColumn<VideoEditListItem>[] = [
    { key: 'reference', header: 'Reference', align: 'left' },
    { key: 'mode', header: 'Mode', sortable: true },
    { key: 'status', header: 'Status', sortable: true },
    {
        key: 'source_count',
        header: 'Clips',
        value: (row) => row.source_count,
        hideOnMobile: true,
    },
    {
        key: 'final_duration_ms',
        header: 'Final length',
        value: (row) => formatDurationMs(row.final_duration_ms),
        sortable: true,
        hideOnMobile: true,
    },
    {
        key: 'applied_cut_count',
        header: 'Cuts',
        value: (row) => row.applied_cut_count,
        sortable: true,
        hideOnMobile: true,
    },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => formatDateShort(row.created_at),
        sortable: true,
        hideOnMobile: true,
    },
];

const sortModel = computed<DataTableSort | null>({
    get: (): DataTableSort => ({
        field: filters.value.sort_field,
        direction: filters.value.sort_order === 1 ? 'asc' : 'desc',
    }),
    set: (value: DataTableSort | null) => {
        const field = SORTABLE.find((candidate) => candidate === value?.field);

        if (!value || !field) {
            return;
        }

        filters.value.sort_field = field;
        filters.value.sort_order = value.direction === 'asc' ? 1 : -1;
        filters.value.page = 1;
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<VideoEditListItem[]>([]);

/** A render in progress cannot be deleted (D14) — mirrors `isDeletable()`. */
const deletableSelection = computed(() =>
    selection.value.filter((row) => row.status !== 'processing'),
);

const createOpen = ref(false);

// ---- single delete ---------------------------------------------------------
const confirmDeleteOpen = ref(false);
const pendingDelete = ref<VideoEditListItem | null>(null);

function requestDelete(row: VideoEditListItem): void {
    pendingDelete.value = row;
    confirmDeleteOpen.value = true;
}

/**
 * The handlers below swallow rejections: each mutation's `onError` already
 * toasted, and `ConfirmModal` fires them without awaiting a result.
 */
async function confirmDelete(): Promise<void> {
    const target = pendingDelete.value;

    if (!target) {
        return;
    }

    try {
        await deleteVideoEdit.mutateAsync(target.uuid);
    } catch {
        return;
    }

    selection.value = selection.value.filter((row) => row.uuid !== target.uuid);
    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

// ---- bulk delete -----------------------------------------------------------
const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteVideoEdits.mutateAsync(
            deletableSelection.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}
</script>

<template>
    <Head title="Video edits" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Video edits</h1>
            <p class="text-sm text-muted-foreground">
                Merge clips, remove silences and filler speech, or let the AI
                cut against your script. Results are private to you.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search by reference…"
                    :max-length="100"
                    aria-label="Search video edits by reference"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
                    presets
                    disable-future
                />

                <FilterSelect
                    class="w-36"
                    :options="modeOptions"
                    placeholder="Any mode"
                    :model-value="filters.mode || null"
                    @update:model-value="onModeChange"
                />

                <FilterSelect
                    class="w-36"
                    :options="statusOptions"
                    placeholder="Any status"
                    :model-value="filters.status || null"
                    @update:model-value="onStatusChange"
                />
            </template>

            <template #bulk>
                <PermissionGuard permission="BULK_DELETE_VIDEO_EDITS">
                    <Button
                        :title="
                            deletableSelection.length === 0
                                ? 'Processing edits cannot be deleted'
                                : undefined
                        "
                        variant="destructive"
                        size="sm"
                        :disabled="
                            deletableSelection.length === 0 ||
                            bulkDeleteVideoEdits.isLoading.value
                        "
                        @click="confirmBulkDeleteOpen = true"
                    >
                        <Trash2Icon class="size-4" aria-hidden="true" />
                        Delete ({{ deletableSelection.length }})
                    </Button>
                </PermissionGuard>
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_VIDEO_EDITS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_VIDEO_EDITS">
                    <Button @click="createOpen = true">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New edit
                    </Button>
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
                v-model:selection="selection"
                :rows="videoEdits"
                :columns="columns"
                :loading="isTableBusy"
                selectable
                caption="Your video edit history"
                empty-title="No video edits yet"
                empty-description="Start an edit to merge clips or clean up a recording."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:reference`]="{ row }">
                    <span class="font-mono text-xs">
                        {{ videoEditReference(row.uuid) }}
                    </span>
                </template>

                <template #[`cell:mode`]="{ row }">
                    <VideoEditModeBadge :mode="row.mode" />
                </template>

                <template #[`cell:status`]="{ row }">
                    <VideoEditStatusBadge
                        :status="row.status"
                        :progress-percent="row.progress_percent"
                    />
                </template>

                <!--
                    No Edit / Restore: an edit is an immutable job and deletion is
                    permanent by decision (spec 001-video-edit Q2/Q5). Download
                    takes the Edit slot for completed rows.
                -->
                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_VIDEO_EDITS">
                        <DataTableRowAction
                            :icon="EyeIcon"
                            :label="`View edit ${videoEditReference(row.uuid)}`"
                            tooltip="View"
                            :href="show.url(row.uuid)"
                            prefetch
                        />
                    </PermissionGuard>

                    <PermissionGuard permission="DOWNLOAD_VIDEO_EDITS">
                        <DataTableRowAction
                            v-if="row.status === 'completed'"
                            :icon="DownloadIcon"
                            :label="`Download edit ${videoEditReference(row.uuid)}`"
                            tooltip="Download"
                            :disabled="downloadVideoEdit.isLoading.value"
                            @click="downloadVideoEdit.mutate(row.uuid)"
                        />
                    </PermissionGuard>

                    <PermissionGuard permission="DELETE_VIDEO_EDITS">
                        <DataTableRowAction
                            v-if="row.status !== 'processing'"
                            :icon="Trash2Icon"
                            :label="`Delete edit ${videoEditReference(row.uuid)}`"
                            tooltip="Delete"
                            destructive
                            @click="requestDelete(row)"
                        />
                    </PermissionGuard>
                </template>
            </DataTable>

            <Paginator
                v-model:page="page"
                :meta="meta"
                :disabled="isTableBusy"
                label="video edits"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <VideoEditFormDialog v-model:open="createOpen" />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this edit permanently?"
        description="The result video, any retained clips and every record of the edit are removed. This cannot be undone."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-mono">
                {{ videoEditReference(pendingDelete.uuid) }}
            </p>
            <p class="text-muted-foreground">
                {{ videoEditModePresentation(pendingDelete.mode).label }} ·
                {{ formatDateShort(pendingDelete.created_at) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${deletableSelection.length} ${deletableSelection.length === 1 ? 'edit' : 'edits'} permanently?`"
        description="Results, retained clips and records are removed. This cannot be undone. Edits still processing are skipped."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="edit in deletableSelection"
                :key="edit.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="font-mono">{{
                    videoEditReference(edit.uuid)
                }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ videoEditModePresentation(edit.mode).label }} ·
                    {{ formatDateShort(edit.created_at) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
