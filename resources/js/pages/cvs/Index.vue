<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    EyeIcon,
    PencilIcon,
    PlusIcon,
    RotateCcwIcon,
    StarIcon,
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
import CvFormDialog from '@/modules/cvs/components/CvFormDialog.vue';
import CvNicheBadge from '@/modules/cvs/components/CvNicheBadge.vue';
import CvStatusBadge from '@/modules/cvs/components/CvStatusBadge.vue';
import { useCvMutations } from '@/modules/cvs/composables/useCvMutations';
import { defaultCvFilters, useCvs } from '@/modules/cvs/composables/useCvs';
import { buildCvQueryParams } from '@/modules/cvs/helpers/buildCvQueryParams';
import {
    CV_NICHES,
    cvFileTypePresentation,
    cvLabel,
    cvNichePresentation,
    formatDate,
} from '@/modules/cvs/helpers/cvPresentation';
import type { Cv, CvNicheFilter, CvStatusFilter } from '@/modules/cvs/types';
import { exportMethod, index, show } from '@/routes/cvs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'CVs', href: index() }],
    },
});

const { cvs, meta: queryMeta, filters, isLoading } = useCvs();

const { deleteCv, restoreCv, bulkDeleteCvs, bulkRestoreCvs } = useCvMutations();

const { can } = usePermissions();

// Restore search / status / niche / date range / page from the URL on load, and
// mirror every later change back with `history.replaceState`. `per_page` is
// fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultCvFilters(),
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

/**
 * The active filter set, as the export endpoint's query string wants it.
 *
 * The same helper the list query calls, so an exported spreadsheet can never
 * show a different set of rows than the table above it.
 */
const exportParams = computed(() => buildCvQueryParams(filters.value));

const exportEndpoint = exportMethod.url();

/**
 * Two options, not the usual three.
 *
 * `EloquentCvRepository::paginate()` only branches on `suspended`
 * (→ `onlyTrashed()`); every other value leaves the `SoftDeletes` global scope
 * in place, and `CvFilterData::rules()` accepts nothing else. An "All" option
 * would therefore return exactly what "Active" returns, and a filter that
 * quietly does nothing is worse than one that is not offered.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

/** The niche facet. Clearing it drops the param entirely — "any niche". */
const nicheOptions = computed<FilterSelectOption[]>(() =>
    CV_NICHES.map((niche) => ({
        value: niche,
        label: cvNichePresentation(niche).label,
    })),
);

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'active'
    ) as CvStatusFilter;
    filters.value.page = 1;
    // The two views never share a row, so a selection made in one is
    // meaningless in the other — and a stale uuid in it would arm a bulk action
    // against a row that is no longer on screen.
    selection.value = [];
}

function onNicheChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.niche = (
        typeof value === 'string' ? value : ''
    ) as CvNicheFilter;
    filters.value.page = 1;
    selection.value = [];
}

const columns: DataTableColumn<Cv>[] = [
    {
        key: 'title',
        header: 'Title',
        align: 'left',
    },
    { key: 'niche', header: 'Niche' },
    {
        key: 'file',
        header: 'File',
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'owner_name',
        header: 'Owner',
        value: (row) => row.owner_name,
        hideOnMobile: true,
    },
    { key: 'status', header: 'Status' },
    {
        key: 'created_at',
        header: 'Uploaded',
        value: (row) => formatDate(row.created_at),
        hideOnMobile: true,
    },
];

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<Cv[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((cv) => cv.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((cv) => cv.deleted_at !== null),
);

function rowClass(row: Cv): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingCv = ref<Cv | null>(null);

function openCreateDialog(): void {
    editingCv.value = null;
    dialogOpen.value = true;
}

function openEditDialog(cv: Cv): void {
    editingCv.value = cv;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Cv | null>(null);

function requestDelete(cv: Cv): void {
    pendingDelete.value = cv;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useCvMutations`) already toasts the failure, so re-throwing here would only
 * surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal`/`DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteCv.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(cv: Cv): Promise<void> {
    await restoreCv.mutateAsync(cv.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteCvs.mutateAsync(
            selectedActive.value.map((cv) => cv.uuid),
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
        await bulkRestoreCvs.mutateAsync(
            selectedDeleted.value.map((cv) => cv.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkRestoreOpen.value = false;
}
</script>

<template>
    <Head title="CVs" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">CVs</h1>
            <p class="text-sm text-muted-foreground">
                {{ meta.total }}
                {{ meta.total === 1 ? 'record' : 'records' }} found.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search title, filename or niche…"
                    aria-label="Search CVs"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Uploaded any time"
                />

                <FilterSelect
                    class="w-40"
                    :options="nicheOptions"
                    placeholder="Any niche"
                    :model-value="filters.niche || null"
                    @update:model-value="onNicheChange"
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
                    :can-delete="can('BULK_DELETE_CVS')"
                    :can-restore="can('BULK_RESTORE_CVS')"
                    :busy="
                        bulkDeleteCvs.isLoading.value ||
                        bulkRestoreCvs.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="confirmBulkRestoreOpen = true"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_CVS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_CVS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        Upload CV
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:selection="selection"
                :rows="cvs"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Résumés stored privately for this account"
                empty-title="No CVs yet"
                empty-description="Upload a PDF or Markdown résumé to start building your library."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:title`]="{ row }">
                    <div class="flex items-center gap-2">
                        <!--
                            The primary CV leads every page — `paginate()` orders
                            by `is_primary` first — so the marker earns its place
                            beside the title rather than in a column of its own.
                        -->
                        <StarIcon
                            v-if="row.is_primary"
                            class="size-4 shrink-0 fill-current text-primary"
                            aria-hidden="true"
                        />
                        <span v-if="row.is_primary" class="sr-only">
                            Primary CV.
                        </span>
                        <span class="truncate font-medium">
                            {{ cvLabel(row) }}
                        </span>
                    </div>
                </template>

                <template #[`cell:niche`]="{ row }">
                    <CvNicheBadge :niche="row.niche" />
                </template>

                <template #[`cell:file`]="{ row }">
                    <div class="flex items-center gap-2">
                        <component
                            :is="cvFileTypePresentation(row.file_type).icon"
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="truncate">
                            {{ row.original_filename }}
                        </span>
                    </div>
                </template>

                <template #[`cell:status`]="{ row }">
                    <CvStatusBadge :deleted-at="row.deleted_at" />
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_CVS">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="View CV"
                        >
                            <Link :href="show(row.uuid)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_CVS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit CV"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_CVS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend CV"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_CVS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore CV"
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
                label="CVs"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <CvFormDialog v-model:open="dialogOpen" :cv="editingCv" />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this CV?"
        description="It will be soft-deleted and hidden from the active list — you can restore it afterwards. The stored file is kept."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ cvLabel(pendingDelete) }}</p>
            <p class="text-muted-foreground">
                {{ pendingDelete.original_filename }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'CV' : 'CVs'}?`"
        description="They will be soft-deleted and hidden from the active list — you can restore them afterwards. The stored files are kept."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="cv in selectedActive"
                :key="cv.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ cvLabel(cv) }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ formatDate(cv.created_at) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${selectedDeleted.length} ${selectedDeleted.length === 1 ? 'CV' : 'CVs'}?`"
        description="They return to the active list."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="cv in selectedDeleted"
                :key="cv.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ cvLabel(cv) }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ formatDate(cv.created_at) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
