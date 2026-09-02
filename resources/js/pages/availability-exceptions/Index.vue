<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarClockIcon,
    EyeIcon,
    PencilIcon,
    PlusIcon,
    RotateCcwIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import type { FilterSelectOption } from '@/common/form';
import { FilterSelect } from '@/common/form';
import type {
    DataTableColumn,
    DateRange,
    PaginationMeta,
} from '@/common/table';
import {
    ConfirmModal,
    DataTable,
    DataTableBulkActions,
    DataTableDateRangeFilter,
    DataTableExportMenu,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import AvailabilityExceptionFormDialog from '@/modules/availability/components/AvailabilityExceptionFormDialog.vue';
import AvailabilityOpenBadge from '@/modules/availability/components/AvailabilityOpenBadge.vue';
import AvailabilityStatusBadge from '@/modules/availability/components/AvailabilityStatusBadge.vue';
import ExceptionSourceBadge from '@/modules/availability/components/ExceptionSourceBadge.vue';
import { useAvailabilityExceptionMutations } from '@/modules/availability/composables/useAvailabilityExceptionMutations';
import {
    buildAvailabilityExceptionQueryParams,
    defaultAvailabilityExceptionFilters,
    useAvailabilityExceptions,
} from '@/modules/availability/composables/useAvailabilityExceptions';
import {
    availabilityExceptionLabel,
    formatDateOnly,
    formatTimeRange,
} from '@/modules/availability/helpers/availabilityPresentation';
import type {
    AvailabilityException,
    AvailabilityStatusFilter,
} from '@/modules/availability/types';
import { exportMethod, index, show } from '@/routes/availability-exceptions';
import { index as rulesIndex } from '@/routes/availability-rules';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Date exceptions', href: index() }],
    },
});

const {
    availabilityExceptions,
    meta: queryMeta,
    filters,
    isLoading,
} = useAvailabilityExceptions();

const {
    deleteException,
    restoreException,
    bulkDeleteExceptions,
    bulkRestoreExceptions,
} = useAvailabilityExceptionMutations();

const { can } = usePermissions();

// Restore search / availability / status / date range / page from the URL on
// load, and mirror every later change back with `history.replaceState`.
// `per_page` is fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultAvailabilityExceptionFilters(),
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

/**
 * Bounds the exception's OWN `date`, not `created_at` — an operator looking for
 * "the December closures" means the days being closed, not the day someone typed
 * them in. `AvailabilityExceptionFilterData` reads it the same way, so the
 * placeholder says so out loud.
 */
const dateRange = computed<DateRange>({
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) => {
        filters.value.date_from = value.from;
        filters.value.date_to = value.to;
        filters.value.page = 1;
    },
});

const availabilityOptions: FilterSelectOption[] = [
    { value: 'all', label: 'Any day' },
    { value: 'open', label: 'Forced open' },
    { value: 'closed', label: 'Closed' },
];

function onAvailabilityChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.availability =
        value === 'open' || value === 'closed' ? value : 'all';
    filters.value.page = 1;
}

/**
 * Two options, not the usual three.
 *
 * `EloquentAvailabilityExceptionRepository::paginate()` only branches on
 * `suspended` (→ `onlyTrashed()`); every other value leaves the `SoftDeletes`
 * global scope in place. An "All" option would return exactly what "Active"
 * returns, and a filter that quietly does nothing is worse than one that is not
 * offered.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        value === 'suspended' ? 'suspended' : 'active'
    ) satisfies AvailabilityStatusFilter;
    filters.value.page = 1;
    // The two views never share a row, so a selection made in one is meaningless
    // in the other — and a stale uuid in it would arm a bulk action against a
    // row that is no longer on screen.
    selection.value = [];
}

/**
 * The active filter set, as the export endpoint's query string wants it. Built
 * by the same helper the list query uses, so the download can never cover a
 * different set than the one on screen — the case
 * `test_search_also_narrows_the_export` pins on the backend.
 */
const exportParams = computed(() =>
    buildAvailabilityExceptionQueryParams(filters.value),
);

const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<AvailabilityException>[] = [
    {
        key: 'date',
        header: 'Date',
        value: (row) => formatDateOnly(row.date),
        align: 'left',
        class: 'min-w-32',
    },
    { key: 'is_available', header: 'Day' },
    {
        key: 'hours',
        header: 'Hours',
        /** Null on a closure — the cell renders an em dash rather than "null". */
        value: (row) => formatTimeRange(row.start_time, row.end_time) ?? '—',
        class: 'min-w-40',
    },
    {
        key: 'reason',
        header: 'Reason',
        value: (row) => row.reason,
        align: 'left',
        hideOnMobile: true,
    },
    { key: 'source', header: 'Source', hideOnMobile: true },
    { key: 'status', header: 'Status' },
];

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<AvailabilityException[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((row) => row.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((row) => row.deleted_at !== null),
);

function rowClass(row: AvailabilityException): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingException = ref<AvailabilityException | null>(null);

function openCreateDialog(): void {
    editingException.value = null;
    dialogOpen.value = true;
}

function openEditDialog(exception: AvailabilityException): void {
    editingException.value = exception;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<AvailabilityException | null>(null);

function requestDelete(exception: AvailabilityException): void {
    pendingDelete.value = exception;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useAvailabilityExceptionMutations`) already toasted the failure, so
 * re-throwing here would only surface as an unhandled rejection with nothing
 * left to do with it — `ConfirmModal`/`DataTable` invoke these as
 * fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteException.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(exception: AvailabilityException): Promise<void> {
    await restoreException.mutateAsync(exception.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteExceptions.mutateAsync(
            selectedActive.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

const confirmBulkRestoreOpen = ref(false);

async function confirmBulkRestore(): Promise<void> {
    try {
        await bulkRestoreExceptions.mutateAsync(
            selectedDeleted.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkRestoreOpen.value = false;
}
</script>

<template>
    <Head title="Date exceptions" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Date exceptions
                </h1>
                <p class="text-sm text-muted-foreground">
                    One-off overrides of the weekly template — closures, and
                    days forced open outside the usual hours.
                    {{ meta.total }}
                    {{ meta.total === 1 ? 'record' : 'records' }} found.
                </p>
            </div>

            <PermissionGuard permission="VIEW_ANY_AVAILABILITY_RULES">
                <Button as-child variant="outline">
                    <Link :href="rulesIndex()">
                        <CalendarClockIcon class="size-4" aria-hidden="true" />
                        Weekly rules
                    </Link>
                </Button>
            </PermissionGuard>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search the reason…"
                    aria-label="Search date exceptions by reason"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Any date"
                />

                <FilterSelect
                    class="w-40"
                    :options="availabilityOptions"
                    :clearable="false"
                    :model-value="filters.availability"
                    @update:model-value="onAvailabilityChange"
                />

                <FilterSelect
                    class="w-40"
                    :options="statusOptions"
                    :clearable="false"
                    :model-value="filters.status"
                    @update:model-value="onStatusChange"
                />
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_AVAILABILITY_EXCEPTIONS')"
                    :can-restore="can('BULK_RESTORE_AVAILABILITY_EXCEPTIONS')"
                    :busy="
                        bulkDeleteExceptions.isLoading.value ||
                        bulkRestoreExceptions.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="confirmBulkRestoreOpen = true"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_AVAILABILITY_EXCEPTIONS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_AVAILABILITY_EXCEPTIONS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New exception
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:selection="selection"
                :rows="availabilityExceptions"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Date-specific overrides of the weekly availability template"
                empty-title="No date exceptions"
                empty-description="Add a closure or a forced-open day to override the weekly template."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:is_available`]="{ row }">
                    <AvailabilityOpenBadge :is-available="row.is_available" />
                </template>

                <template #[`cell:source`]="{ row }">
                    <ExceptionSourceBadge :source="row.source" />
                </template>

                <template #[`cell:status`]="{ row }">
                    <AvailabilityStatusBadge :deleted-at="row.deleted_at" />
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_AVAILABILITY_EXCEPTIONS">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="View date exception"
                        >
                            <Link :href="show(row.uuid)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>

                    <template v-if="row.deleted_at === null">
                        <PermissionGuard
                            permission="UPDATE_AVAILABILITY_EXCEPTIONS"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit date exception"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard
                            permission="DELETE_AVAILABILITY_EXCEPTIONS"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend date exception"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <!-- Never Edit on a suspended row: restore it first. -->
                    <PermissionGuard
                        v-else
                        permission="RESTORE_AVAILABILITY_EXCEPTIONS"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore date exception"
                            @click="onRestoreRow(row)"
                        >
                            <RotateCcwIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>
                </template>
            </DataTable>

            <Paginator
                v-model:page="page"
                :meta="meta"
                :disabled="isLoading"
                label="date exceptions"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <AvailabilityExceptionFormDialog
        v-model:open="dialogOpen"
        :exception="editingException"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this date exception?"
        description="It will be soft-deleted and the day falls back to the weekly template — you can restore it afterwards."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">
                {{ availabilityExceptionLabel(pendingDelete) }}
            </p>
            <p class="text-muted-foreground">
                {{ pendingDelete.is_available ? 'Forced open' : 'Closed' }}
                <template v-if="pendingDelete.source === 'holiday'">
                    · rebuilt by the next holiday sync
                </template>
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'exception' : 'exceptions'}?`"
        description="Those days fall back to the weekly template — you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="exception in selectedActive"
                :key="exception.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ formatDateOnly(exception.date) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ exception.reason || '—' }}
                </span>
            </li>
        </ul>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${selectedDeleted.length} ${selectedDeleted.length === 1 ? 'exception' : 'exceptions'}?`"
        description="They override the weekly template again. One whose date has since been claimed by another active exception is rejected."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="exception in selectedDeleted"
                :key="exception.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ formatDateOnly(exception.date) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ exception.reason || '—' }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
