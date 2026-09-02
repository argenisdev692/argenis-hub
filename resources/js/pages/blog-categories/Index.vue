<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    EyeIcon,
    ImageIcon,
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
import BlogCategoryFormDialog from '@/modules/blog-categories/components/BlogCategoryFormDialog.vue';
import BlogCategoryStatusBadge from '@/modules/blog-categories/components/BlogCategoryStatusBadge.vue';
import {
    defaultBlogCategoryFilters,
    useBlogCategories,
} from '@/modules/blog-categories/composables/useBlogCategories';
import { useBlogCategoryMutations } from '@/modules/blog-categories/composables/useBlogCategoryMutations';
import {
    blogCategoryAuthorName,
    blogCategoryLabel,
    formatDate,
} from '@/modules/blog-categories/helpers/blogCategoryPresentation';
import type {
    BlogCategory,
    BlogCategoryStatusFilter,
} from '@/modules/blog-categories/types';
import { exportMethod, index, show } from '@/routes/blog-categories';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Blog categories', href: index() }],
    },
});

const {
    blogCategories,
    meta: queryMeta,
    filters,
    isLoading,
} = useBlogCategories();

const {
    deleteBlogCategory,
    restoreBlogCategory,
    bulkDeleteBlogCategories,
    bulkRestoreBlogCategories,
} = useBlogCategoryMutations();

const { can } = usePermissions();

// Restore search / status / date range / page from the URL on load, and mirror
// every later change back with `history.replaceState`. `per_page` is fixed, so
// it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultBlogCategoryFilters(),
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
    status: filters.value.status,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
}));

const exportEndpoint = exportMethod.url();

/**
 * Two options, not the usual three.
 *
 * `EloquentBlogCategoryRepository::paginate()` only branches on `suspended`
 * (→ `onlyTrashed()`); every other value leaves the `SoftDeletes` global scope
 * in place. An "All" option would therefore return exactly what "Active"
 * returns, and a filter that quietly does nothing is worse than one that is
 * not offered.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'active'
    ) as BlogCategoryStatusFilter;
    filters.value.page = 1;
    // The two views never share a row, so a selection made in one is
    // meaningless in the other — and a stale uuid in it would arm a bulk
    // action against a row that is no longer on screen.
    selection.value = [];
}

const columns: DataTableColumn<BlogCategory>[] = [
    { key: 'image', header: 'Image', class: 'w-20' },
    {
        key: 'blog_category_name',
        header: 'Name',
        value: (row) => row.blog_category_name,
        align: 'left',
    },
    {
        key: 'blog_category_description',
        header: 'Description',
        value: (row) => row.blog_category_description,
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'author',
        header: 'Author',
        value: (row) => blogCategoryAuthorName(row),
        hideOnMobile: true,
    },
    { key: 'status', header: 'Status' },
    {
        key: 'created_at',
        header: 'Created',
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

const selection = ref<BlogCategory[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((category) => category.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((category) => category.deleted_at !== null),
);

function rowClass(row: BlogCategory): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingCategory = ref<BlogCategory | null>(null);

function openCreateDialog(): void {
    editingCategory.value = null;
    dialogOpen.value = true;
}

function openEditDialog(category: BlogCategory): void {
    editingCategory.value = category;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<BlogCategory | null>(null);

function requestDelete(category: BlogCategory): void {
    pendingDelete.value = category;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useBlogCategoryMutations`) already toasts the failure, so re-throwing here
 * would only surface as an unhandled rejection with nothing left to do with it
 * — `ConfirmModal`/`DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteBlogCategory.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(category: BlogCategory): Promise<void> {
    await restoreBlogCategory.mutateAsync(category.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteBlogCategories.mutateAsync(
            selectedActive.value.map((category) => category.uuid),
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
        await bulkRestoreBlogCategories.mutateAsync(
            selectedDeleted.value.map((category) => category.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkRestoreOpen.value = false;
}
</script>

<template>
    <Head title="Blog categories" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Blog categories
            </h1>
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
                    placeholder="Search name or description…"
                    aria-label="Search blog categories"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
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
                    :can-delete="can('BULK_DELETE_BLOG_CATEGORIES')"
                    :can-restore="can('BULK_RESTORE_BLOG_CATEGORIES')"
                    :busy="
                        bulkDeleteBlogCategories.isLoading.value ||
                        bulkRestoreBlogCategories.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="confirmBulkRestoreOpen = true"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_BLOG_CATEGORIES">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_BLOG_CATEGORIES">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New category
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:selection="selection"
                :rows="blogCategories"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Blog categories used to group posts"
                empty-title="No blog categories yet"
                empty-description="Create the first category to start filing posts under it."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:image`]="{ row }">
                    <img
                        v-if="row.image_url"
                        :src="row.image_url"
                        :alt="`Image for ${blogCategoryLabel(row)}`"
                        class="size-10 rounded-md object-cover"
                        loading="lazy"
                    />
                    <span
                        v-else
                        class="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground"
                        role="img"
                        aria-label="No image"
                    >
                        <ImageIcon class="size-4" aria-hidden="true" />
                    </span>
                </template>

                <template #[`cell:status`]="{ row }">
                    <BlogCategoryStatusBadge :deleted-at="row.deleted_at" />
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_BLOG_CATEGORIES">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="View blog category"
                        >
                            <Link :href="show(row.uuid)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_BLOG_CATEGORIES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit blog category"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_BLOG_CATEGORIES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend blog category"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard
                        v-else
                        permission="RESTORE_BLOG_CATEGORIES"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore blog category"
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
                label="blog categories"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <BlogCategoryFormDialog
        v-model:open="dialogOpen"
        :category="editingCategory"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this blog category?"
        description="It will be soft-deleted and hidden from the public feed — you can restore it afterwards. Its posts are not deleted."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ blogCategoryLabel(pendingDelete) }}</p>
            <p class="text-muted-foreground">
                {{ blogCategoryAuthorName(pendingDelete) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'blog category' : 'blog categories'}?`"
        description="They will be soft-deleted and hidden from the public feed — you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="category in selectedActive"
                :key="category.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ blogCategoryLabel(category) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ formatDate(category.created_at) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${selectedDeleted.length} ${selectedDeleted.length === 1 ? 'blog category' : 'blog categories'}?`"
        description="They return to the active list and to the public feed."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="category in selectedDeleted"
                :key="category.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ blogCategoryLabel(category) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ formatDate(category.created_at) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
