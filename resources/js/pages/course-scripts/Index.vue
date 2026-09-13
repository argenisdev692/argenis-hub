<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { EyeIcon, PlusIcon, RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import type { FilterSelectOption, FilterSelectValue } from '@/common/form';
import { FilterSelect } from '@/common/form';
import type { DataTableColumn, DataTableSort, DateRange } from '@/common/table';
import {
    ConfirmModal,
    DataTable,
    DataTableBulkActions,
    DataTableDateRangeFilter,
    DataTableExportMenu,
    DataTableRowAction,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import CourseStatusBadge from '@/modules/course-scripts/components/CourseStatusBadge.vue';
import CourseTitleList from '@/modules/course-scripts/components/CourseTitleList.vue';
import { useCourseMutations } from '@/modules/course-scripts/composables/useCourseMutations';
import {
    COURSE_SEARCH_MAX_LENGTH,
    defaultCourseFilters,
    useCourses,
} from '@/modules/course-scripts/composables/useCourses';
import { buildCourseQueryParams } from '@/modules/course-scripts/helpers/buildCourseQueryParams';
import {
    COURSE_STATUSES_ORDERED,
    courseStatusPresentation,
    formatDate,
    generatedProgress,
    pluralize,
} from '@/modules/course-scripts/helpers/coursePresentation';
import type {
    CourseListItem,
    CourseSortField,
    CourseStatus,
    CourseTrashedFilter,
} from '@/modules/course-scripts/types';
import { create, exportMethod, index, show } from '@/routes/course-scripts';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Course scripts', href: index() }],
    },
});

const { courses, meta, filters, isLoading } = useCourses();

const { deleteCourse, restoreCourse, bulkDeleteCourses, bulkRestoreCourses } =
    useCourseMutations();

const { can } = usePermissions();

useUrlSyncedFilters(filters, {
    defaults: defaultCourseFilters(),
    exclude: ['per_page'],
});

const selection = ref<CourseListItem[]>([]);

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});

/**
 * Any change to what the list shows resets paging and the selection — a uuid
 * selected under another filter would arm a bulk action on an off-screen row.
 */
function applyFilter(mutate: () => void): void {
    mutate();
    filters.value.page = 1;
    selection.value = [];
}

const searchTerm = computed<string>({
    get: () => filters.value.search,
    set: (value) => applyFilter(() => (filters.value.search = value)),
});

const dateRange = computed<DateRange>({
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) =>
        applyFilter(() => {
            filters.value.date_from = value.from;
            filters.value.date_to = value.to;
        }),
});

const SORT_FIELDS: readonly CourseSortField[] = [
    'created_at',
    'updated_at',
    'title',
    'status',
];

function isSortField(field: string): field is CourseSortField {
    return SORT_FIELDS.some((sortField) => sortField === field);
}

const sort = computed<DataTableSort | null>({
    get: (): DataTableSort => ({
        field: filters.value.sort_field,
        direction: filters.value.sort_order === 1 ? 'asc' : 'desc',
    }),
    set: (value: DataTableSort | null) => {
        const defaults = defaultCourseFilters();

        filters.value.sort_field =
            value && isSortField(value.field)
                ? value.field
                : defaults.sort_field;
        filters.value.sort_order = value
            ? value.direction === 'asc'
                ? 1
                : -1
            : defaults.sort_order;
        filters.value.page = 1;
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => (filters.value.page = value),
});

const statusOptions: FilterSelectOption[] = COURSE_STATUSES_ORDERED.map(
    (status) => ({
        value: status,
        label: courseStatusPresentation(status).label,
    }),
);

/** What `FilterSelect`'s model emits — null when cleared. */
type FilterSelectModel = FilterSelectValue | FilterSelectValue[] | null;

const trashedOptions: FilterSelectOption[] = [
    { value: 'without', label: 'Active' },
    { value: 'only', label: 'Deleted' },
    { value: 'with', label: 'All' },
];

function isCourseStatus(value: unknown): value is CourseStatus {
    return COURSE_STATUSES_ORDERED.some((status) => status === value);
}

function isTrashedFilter(value: unknown): value is CourseTrashedFilter {
    return trashedOptions.some((option) => option.value === value);
}

function onStatusChange(value: FilterSelectModel): void {
    applyFilter(
        () => (filters.value.status = isCourseStatus(value) ? value : ''),
    );
}

function onTrashedChange(value: FilterSelectModel): void {
    applyFilter(
        () =>
            (filters.value.trashed = isTrashedFilter(value)
                ? value
                : 'without'),
    );
}

/** Same helper as the list query — the export always matches the table. */
const exportParams = computed(() => buildCourseQueryParams(filters.value));
const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<CourseListItem>[] = [
    { key: 'title', header: 'Title', align: 'left', sortable: true },
    {
        key: 'language',
        header: 'Language',
        value: (row) => row.language.toUpperCase(),
        hideOnMobile: true,
    },
    { key: 'videos', header: 'Scripts generated', hideOnMobile: true },
    { key: 'status', header: 'Status', sortable: true },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => formatDate(row.created_at),
        sortable: true,
        hideOnMobile: true,
    },
    {
        key: 'updated_at',
        header: 'Updated',
        value: (row) => formatDate(row.updated_at),
        sortable: true,
        hideOnMobile: true,
    },
];

function rowClass(row: CourseListItem): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const selectedActive = computed(() =>
    selection.value.filter((course) => course.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((course) => course.deleted_at !== null),
);

/**
 * The modals await these handlers (`ConfirmModal` stays busy until they
 * settle) and close only on success. Rejections are swallowed here because
 * each mutation's `onError` has already toasted; the open dialog lets the user
 * retry.
 */
async function succeeded(work: Promise<unknown>): Promise<boolean> {
    try {
        await work;
    } catch {
        return false;
    }

    return true;
}

function deselect(uuids: readonly string[]): void {
    selection.value = selection.value.filter(
        (course) => !uuids.includes(course.uuid),
    );
}

// ---- single delete / restore ----------------------------------------------
const pendingDelete = ref<CourseListItem | null>(null);
const confirmDeleteOpen = ref(false);

const pendingRestore = ref<CourseListItem | null>(null);
const confirmRestoreOpen = ref(false);

function requestDelete(course: CourseListItem): void {
    pendingDelete.value = course;
    confirmDeleteOpen.value = true;
}

function requestRestore(course: CourseListItem): void {
    pendingRestore.value = course;
    confirmRestoreOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    const target = pendingDelete.value;

    if (target && (await succeeded(deleteCourse.mutateAsync(target.uuid)))) {
        deselect([target.uuid]);
        confirmDeleteOpen.value = false;
    }
}

async function confirmRestore(): Promise<void> {
    const target = pendingRestore.value;

    if (target && (await succeeded(restoreCourse.mutateAsync(target.uuid)))) {
        deselect([target.uuid]);
        confirmRestoreOpen.value = false;
    }
}

// ---- bulk delete / restore ------------------------------------------------
const confirmBulkDeleteOpen = ref(false);
const confirmBulkRestoreOpen = ref(false);

async function runBulk(
    mutation: typeof bulkDeleteCourses,
    rows: readonly CourseListItem[],
): Promise<boolean> {
    const uuids = rows.map((course) => course.uuid);

    if (!(await succeeded(mutation.mutateAsync(uuids)))) {
        return false;
    }

    deselect(uuids);

    return true;
}

async function confirmBulkDelete(): Promise<void> {
    if (await runBulk(bulkDeleteCourses, selectedActive.value)) {
        confirmBulkDeleteOpen.value = false;
    }
}

async function confirmBulkRestore(): Promise<void> {
    if (await runBulk(bulkRestoreCourses, selectedDeleted.value)) {
        confirmBulkRestoreOpen.value = false;
    }
}
</script>

<template>
    <Head title="Course scripts" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Course scripts
            </h1>
            <p class="text-sm text-muted-foreground" aria-live="polite">
                {{ recordCounter }}
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search by title…"
                    :max-length="COURSE_SEARCH_MAX_LENGTH"
                    aria-label="Search courses by title"
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
                    class="w-48"
                    :options="statusOptions"
                    placeholder="Any status"
                    :model-value="filters.status || null"
                    @update:model-value="onStatusChange"
                />

                <FilterSelect
                    class="w-36"
                    :options="trashedOptions"
                    :clearable="false"
                    :model-value="filters.trashed"
                    @update:model-value="onTrashedChange"
                />
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('DELETE_COURSE_SCRIPTS')"
                    :can-restore="can('RESTORE_COURSE_SCRIPTS')"
                    :busy="
                        bulkDeleteCourses.isLoading.value ||
                        bulkRestoreCourses.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="confirmBulkRestoreOpen = true"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_COURSE_SCRIPTS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_COURSE_SCRIPTS">
                    <Button as-child>
                        <Link :href="create()" prefetch>
                            <PlusIcon class="size-4" aria-hidden="true" />
                            New course
                        </Link>
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:selection="selection"
                v-model:sort="sort"
                :rows="courses"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Your courses and their script generation progress"
                empty-title="No courses found"
                empty-description="Upload a course index to start generating video scripts."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:title`]="{ row }">
                    <span class="truncate font-medium">{{ row.title }}</span>
                </template>

                <template #[`cell:videos`]="{ row }">
                    <span class="tabular-nums">
                        {{
                            generatedProgress(
                                row.generated_videos_count,
                                row.videos_count,
                            )
                        }}
                    </span>
                </template>

                <template #[`cell:status`]="{ row }">
                    <CourseStatusBadge
                        :status="row.status"
                        :deleted-at="row.deleted_at"
                    />
                </template>

                <!--
                    No Edit action: a course has no single edit form — its notes,
                    bible and video briefs are edited in place on the course page.
                    No View on a deleted row: `show` resolves live courses only.
                -->
                <template #actions="{ row }">
                    <template v-if="row.deleted_at === null">
                        <PermissionGuard permission="VIEW_COURSE_SCRIPTS">
                            <DataTableRowAction
                                :icon="EyeIcon"
                                :label="`View ${row.title}`"
                                tooltip="View"
                                :href="show.url(row.uuid)"
                            />
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_COURSE_SCRIPTS">
                            <DataTableRowAction
                                :icon="Trash2Icon"
                                :label="`Delete ${row.title}`"
                                tooltip="Delete"
                                destructive
                                @click="requestDelete(row)"
                            />
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_COURSE_SCRIPTS">
                        <DataTableRowAction
                            :icon="RotateCcwIcon"
                            :label="`Restore ${row.title}`"
                            tooltip="Restore"
                            @click="requestRestore(row)"
                        />
                    </PermissionGuard>
                </template>
            </DataTable>

            <Paginator
                v-model:page="page"
                :meta="meta"
                :disabled="isLoading"
                label="courses"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this course?"
        description="It moves to the Deleted view and can be restored later. Generated scripts are kept."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <CourseTitleList v-if="pendingDelete" :courses="[pendingDelete]" />
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmRestoreOpen"
        title="Restore this course?"
        description="It returns to the active list with its scripts intact."
        confirm-label="Restore"
        @confirm="confirmRestore"
    >
        <CourseTitleList v-if="pendingRestore" :courses="[pendingRestore]" />
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${pluralize(selectedActive.length, 'course', 'courses')}?`"
        description="They move to the Deleted view and can be restored later."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <CourseTitleList :courses="selectedActive" />
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${pluralize(selectedDeleted.length, 'course', 'courses')}?`"
        description="They return to the active list."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <CourseTitleList :courses="selectedDeleted" />
    </ConfirmModal>
</template>
