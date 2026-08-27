<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { DownloadIcon, EyeIcon, PlayIcon, Trash2Icon } from '@lucide/vue';
import { computed, ref } from 'vue';
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
    ConfirmModal,
    DataTable,
    DataTableDateRangeFilter,
    DataTableExportMenu,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import { toUrl } from '@/lib/utils';
import BackupDetailDialog from '@/modules/backups/components/BackupDetailDialog.vue';
import BackupStatusBadge from '@/modules/backups/components/BackupStatusBadge.vue';
import { useBackupMutations } from '@/modules/backups/composables/useBackupMutations';
import {
    defaultBackupFilters,
    useBackups,
} from '@/modules/backups/composables/useBackups';
import { formatBackupTimestamp } from '@/modules/backups/helpers/formatBackupTimestamp';
import type { Backup, BackupStatusFilter } from '@/modules/backups/types';
import { index } from '@/routes/backups';
import { download, exportMethod } from '@/routes/backups/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Backups', href: index() }],
    },
});

const { backups, meta: queryMeta, filters, isLoading } = useBackups();
const { runBackup, deleteBackup, bulkDeleteBackups } = useBackupMutations();

// Restore search / status / date range / page / sort from the URL on load, and
// mirror every later change back with `history.replaceState`. `per_page` is
// fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultBackupFilters(),
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

/** `DataTableSearch` debounces internally; committing a term resets the page. */
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

/** The active filter set, as the export endpoint's query string wants it. */
const exportParams = computed(() => ({
    search: filters.value.search || undefined,
    status: filters.value.status === 'all' ? undefined : filters.value.status,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
    sort_field: filters.value.sort_field,
    sort_order: filters.value.sort_order,
}));

const exportEndpoint = exportMethod.url();

const statusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All' },
    { value: 'running', label: 'Running' },
    { value: 'completed', label: 'Completed' },
    { value: 'failed', label: 'Failed' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as BackupStatusFilter;
    filters.value.page = 1;
}

const columns: DataTableColumn<Backup>[] = [
    {
        key: 'filename',
        header: 'File name',
        value: (row) => row.filename,
        sortable: true,
        align: 'left',
    },
    { key: 'status', header: 'Status' },
    {
        key: 'size_bytes',
        header: 'Size',
        value: (row) => row.human_size,
        sortable: true,
        align: 'right',
    },
    {
        key: 'connection',
        header: 'Connection',
        value: (row) => row.connection,
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => formatBackupTimestamp(row.created_at),
        sortable: true,
        hideOnMobile: true,
    },
    {
        key: 'finished_at',
        header: 'Finished',
        value: (row) => formatBackupTimestamp(row.finished_at),
        hideOnMobile: true,
    },
];

const sortModel = computed<DataTableSort | null>({
    get: (): DataTableSort => ({
        field: filters.value.sort_field,
        direction: filters.value.sort_order === 1 ? 'asc' : 'desc',
    }),
    set: (value: DataTableSort | null) => {
        if (!value) {
            return;
        }

        filters.value.sort_field =
            value.field as typeof filters.value.sort_field;
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

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});

const selection = ref<Backup[]>([]);

/** A failed run has no archive on disk; a running one is not finished yet. */
function canDownloadRow(row: Backup): boolean {
    return row.status === 'completed' && Boolean(row.path);
}

function downloadRow(row: Backup): void {
    window.location.assign(toUrl(download(row.uuid)));
}

// ---- detail dialog -------------------------------------------------------
const detailUuid = ref<string | null>(null);
const detailOpen = ref(false);

function openDetail(row: Backup): void {
    detailUuid.value = row.uuid;
    detailOpen.value = true;
}

// ---- run a backup now --------------------------------------------------
const confirmRunOpen = ref(false);

/**
 * Each handler below catches and discards: the mutation's own `onError`
 * (in `useBackupMutations`) already toasts the failure, so re-throwing here
 * would only surface as an unhandled rejection — `ConfirmModal` invokes these
 * as fire-and-forget.
 */
async function confirmRun(): Promise<void> {
    try {
        await runBackup.mutateAsync();
    } catch {
        return;
    }

    confirmRunOpen.value = false;
}

// ---- single delete ---------------------------------------------------------
const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Backup | null>(null);

function requestDelete(row: Backup): void {
    pendingDelete.value = row;
    confirmDeleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    const target = pendingDelete.value;

    if (!target) {
        return;
    }

    try {
        await deleteBackup.mutateAsync(target.uuid);
    } catch {
        return;
    }

    selection.value = selection.value.filter((row) => row.uuid !== target.uuid);
    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

// ---- bulk delete ---------------------------------------------------------
const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteBackups.mutateAsync(
            selection.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}
</script>

<template>
    <Head title="Backups" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Backups</h1>
            <p class="text-sm text-muted-foreground">
                Database snapshots taken on a schedule or on demand. Archives
                are immutable — download or delete what exists, or trigger a
                fresh run.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search file name, connection or disk…"
                    aria-label="Search backups"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
                />

                <FilterSelect
                    class="w-36"
                    :options="statusOptions"
                    :clearable="false"
                    :model-value="filters.status"
                    @update:model-value="onStatusChange"
                />
            </template>

            <template #bulk>
                <PermissionGuard permission="BULK_DELETE_BACKUPS">
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="bulkDeleteBackups.isLoading.value"
                        @click="confirmBulkDeleteOpen = true"
                    >
                        <Trash2Icon class="size-4" aria-hidden="true" />
                        Delete ({{ selection.length }})
                    </Button>
                </PermissionGuard>
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_BACKUPS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['csv', 'xlsx', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_BACKUPS">
                    <Button
                        :disabled="runBackup.isLoading.value"
                        @click="confirmRunOpen = true"
                    >
                        <PlayIcon class="size-4" aria-hidden="true" />
                        Run backup
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
                :rows="backups"
                :columns="columns"
                :loading="isLoading"
                selectable
                caption="Database backup archives"
                empty-title="No backups yet"
                empty-description="Run a backup now, or wait for the next scheduled one."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:status`]="{ row }">
                    <BackupStatusBadge :status="row.status" />
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_BACKUPS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View backup"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <PermissionGuard permission="DOWNLOAD_BACKUPS">
                        <Button
                            v-if="canDownloadRow(row)"
                            variant="ghost"
                            size="icon"
                            aria-label="Download backup"
                            @click="downloadRow(row)"
                        >
                            <DownloadIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <PermissionGuard permission="DELETE_BACKUPS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Delete backup"
                            @click="requestDelete(row)"
                        >
                            <Trash2Icon
                                class="size-4 text-destructive"
                                aria-hidden="true"
                            />
                        </Button>
                    </PermissionGuard>
                </template>
            </DataTable>

            <Paginator
                v-model:page="page"
                :meta="meta"
                :disabled="isLoading"
                label="backups"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <BackupDetailDialog v-model:open="detailOpen" :uuid="detailUuid" />

    <ConfirmModal
        v-model:open="confirmRunOpen"
        title="Run a backup now?"
        description="A fresh database snapshot will be queued. It appears in the list once the job finishes."
        confirm-label="Run backup"
        @confirm="confirmRun"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this backup?"
        description="The archive is removed from storage permanently — this cannot be undone."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium break-all">{{ pendingDelete.filename }}</p>
            <p class="text-muted-foreground">{{ pendingDelete.human_size }}</p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${selection.length} ${selection.length === 1 ? 'backup' : 'backups'}?`"
        description="The archives are removed from storage permanently — this cannot be undone."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="backup in selection"
                :key="backup.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ backup.filename }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ backup.human_size }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
