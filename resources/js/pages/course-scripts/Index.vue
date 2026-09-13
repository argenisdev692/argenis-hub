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
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import CourseStatusBadge from '@/modules/course-scripts/components/CourseStatusBadge.vue';
import { useCourseMutations } from '@/modules/course-scripts/composables/useCourseMutations';
import {
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

const pendingDelete = ref<CourseListItem | null>(null);
const confirmDeleteOpen = ref(false);
const confirmBulkDeleteOpen = ref(false);
const confirmBulkRestoreOpen = ref(false);

function requestDelete(course: CourseListItem): void {
    pendingDelete.value = course;
    confirmDeleteOpen.value = true;
}

/**
 * The handlers swallow rejections: each mutation's `onError` already toasts,
 * and the modals keep their dialog open on failure so the user can retry.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteCourse.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(course: CourseListItem): Promise<void> {
    await restoreCourse.mutateAsync(course.uuid).catch(() => undefined);
}

async function runBulk(
    mutation: typeof bulkDeleteCourses,
    rows: CourseListItem[],
): Promise<boolean> {
    try {
        await mutation.mutateAsync(rows.map((course) => course.uuid));
    } catch {
        return false;
    }

    selection.value = [];

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
                    placeholder="Search courses…"
                    aria-label="Search courses"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
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

                <template #actions="{ row }">
                    <PermissionGuard
                        v-if="!row.deleted_at"
                        permission="VIEW_COURSE_SCRIPTS"
                    >
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            :aria-label="`View ${row.title}`"
                        >
                            <Link :href="show(row.uuid)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>

                    <PermissionGuard
                        v-if="!row.deleted_at"
                        permission="DELETE_COURSE_SCRIPTS"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Delete ${row.title}`"
                            @click="requestDelete(row)"
                        >
                            <Trash2Icon
                                class="size-4 text-destructive"
                                aria-hidden="true"
                            />
                        </Button>
                    </PermissionGuard>

                    <PermissionGuard v-else permission="RESTORE_COURSE_SCRIPTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Restore ${row.title}`"
                            :disabled="restoreCourse.isLoading.value"
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
        <p
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm font-medium"
        >
            {{ pendingDelete.title }}
        </p>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${pluralize(selectedActive.length, 'course', 'courses')}?`"
        description="They move to the Deleted view and can be restored later."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="course in selectedActive"
                :key="course.uuid"
                class="truncate font-medium"
            >
                {{ course.title }}
            </li>
        </ul>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${pluralize(selectedDeleted.length, 'course', 'courses')}?`"
        description="They return to the active list."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="course in selectedDeleted"
                :key="course.uuid"
                class="truncate font-medium"
            >
                {{ course.title }}
            </li>
        </ul>
    </ConfirmModal>
</template>
