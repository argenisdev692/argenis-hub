<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    EyeIcon,
    PencilIcon,
    PlusIcon,
    RotateCcwIcon,
    SparklesIcon,
    Trash2Icon,
} from '@lucide/vue';
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
    DataTableBulkActions,
    DataTableDateRangeFilter,
    DataTableExportMenu,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import PostQualityScores from '@/modules/posts/components/PostQualityScores.vue';
import PostStatusBadge from '@/modules/posts/components/PostStatusBadge.vue';
import { usePostMutations } from '@/modules/posts/composables/usePostMutations';
import {
    defaultPostFilters,
    usePosts,
} from '@/modules/posts/composables/usePosts';
import {
    formatDate,
    postAuthorName,
} from '@/modules/posts/helpers/postPresentation';
import type {
    PostCategoryOption,
    PostListItem,
    PostSortField,
    PostStatusFilter,
} from '@/modules/posts/types';
import { create, edit, exportMethod, index, show } from '@/routes/posts';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Posts', href: index() }],
    },
});

/**
 * `categories` arrives as an Inertia prop because it is small, static and
 * needed to render the filter on first paint. The list itself does NOT — it
 * comes from `usePosts()` over XHR, which is what gives the table a cache the
 * row actions can invalidate and a previous page to hold while the next loads.
 */
const { categories } = defineProps<{
    categories: PostCategoryOption[];
}>();

const { posts, meta: queryMeta, filters, isLoading } = usePosts();
const { deletePost, restorePost, bulkDeletePosts, bulkRestorePosts } =
    usePostMutations();

const { can } = usePermissions();

// Restore search / status / category / date range / page / sort from the URL on
// load, and mirror every later change back with `history.replaceState`.
// `per_page` is fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultPostFilters(),
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

const statusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'suspended', label: 'Suspended' },
];

const categoryOptions = computed<FilterSelectOption[]>(() =>
    categories.map((category) => ({
        value: category.value,
        label: category.label,
    })),
);

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as PostStatusFilter;
    filters.value.page = 1;
}

function onCategoryChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.category_uuid = typeof value === 'string' ? value : null;
    filters.value.page = 1;
}

/** The active filter set, as the export endpoint's query string wants it. */
const exportParams = computed(() => ({
    search: filters.value.search || undefined,
    status: filters.value.status === 'all' ? undefined : filters.value.status,
    category_uuid: filters.value.category_uuid ?? undefined,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
    sort_field: filters.value.sort_field,
    sort_order: filters.value.sort_order,
}));

const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<PostListItem>[] = [
    {
        key: 'post_title',
        header: 'Title',
        sortable: true,
        align: 'left',
        class: 'min-w-64',
    },
    { key: 'category', header: 'Category', align: 'left', hideOnMobile: true },
    { key: 'post_status', header: 'Status', sortable: true },
    {
        key: 'published_at',
        header: 'Published',
        sortable: true,
        hideOnMobile: true,
    },
    { key: 'seo_score', header: 'Quality', sortable: true, hideOnMobile: true },
    {
        key: 'user',
        header: 'Author',
        value: (row) => postAuthorName(row),
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => formatDate(row.created_at),
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
        if (!value) {
            return;
        }

        filters.value.sort_field = value.field as PostSortField;
        filters.value.sort_order = value.direction === 'asc' ? 1 : -1;
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<PostListItem[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((post) => post.deleted_at === null),
);

function rowClass(row: PostListItem): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<PostListItem | null>(null);

function requestDelete(post: PostListItem): void {
    pendingDelete.value = post;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `usePostMutations`) already toasted the failure, so re-throwing here would
 * only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal` and `DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deletePost.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(post: PostListItem): Promise<void> {
    await restorePost.mutateAsync(post.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeletePosts.mutateAsync(
            selectedActive.value.map((post) => post.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(uuids: string[]): Promise<void> {
    try {
        await bulkRestorePosts.mutateAsync(uuids);
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Posts" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Posts</h1>
            <p class="text-sm text-muted-foreground">
                Blog articles. Published posts feed the public feed; scheduled
                ones go live on the next scheduler run.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search title or excerpt…"
                    aria-label="Search posts"
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

                <FilterSelect
                    class="w-48"
                    :options="categoryOptions"
                    placeholder="All categories"
                    search-placeholder="Search categories…"
                    :model-value="filters.category_uuid"
                    @update:model-value="onCategoryChange"
                />
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_POSTS')"
                    :can-restore="can('BULK_RESTORE_POSTS')"
                    :busy="
                        bulkDeletePosts.isLoading.value ||
                        bulkRestorePosts.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_POSTS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_POSTS">
                    <Button as-child>
                        <Link :href="create()" prefetch>
                            <PlusIcon class="size-4" aria-hidden="true" />
                            New post
                        </Link>
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:sort="sortModel"
                v-model:selection="selection"
                :rows="posts"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Blog posts"
                empty-title="No posts yet"
                empty-description="Write the first one, or let the AI assist panel draft it."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:post_title`]="{ row }">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="flex items-center gap-1.5 font-medium">
                            <SparklesIcon
                                v-if="row.is_ai_generated"
                                class="size-3.5 shrink-0 text-muted-foreground"
                                aria-label="AI generated"
                            />
                            <Link
                                :href="show(row.uuid)"
                                class="truncate rounded-sm hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                {{ row.post_title }}
                            </Link>
                        </span>
                        <span
                            v-if="row.post_excerpt"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ row.post_excerpt }}
                        </span>
                    </div>
                </template>

                <template #[`cell:category`]="{ row }">
                    <Badge v-if="row.category" variant="secondary">
                        {{ row.category.blog_category_name }}
                    </Badge>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #[`cell:post_status`]="{ row }">
                    <PostStatusBadge
                        :status="row.post_status"
                        :deleted-at="row.deleted_at"
                    />
                </template>

                <template #[`cell:published_at`]="{ row }">
                    <span v-if="row.published_at">
                        {{ formatDate(row.published_at) }}
                    </span>
                    <span
                        v-else-if="row.scheduled_at"
                        class="text-muted-foreground"
                    >
                        {{ formatDate(row.scheduled_at) }}
                    </span>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #[`cell:seo_score`]="{ row }">
                    <PostQualityScores
                        v-if="row.seo_score !== null"
                        compact
                        class="min-w-32 text-left"
                        :seo-score="row.seo_score"
                        :eeat-score="row.eeat_score"
                        :human-writing-index="row.human_writing_index"
                    />
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_POSTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            as-child
                            aria-label="View post"
                        >
                            <Link :href="show(row.uuid)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_POSTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                as-child
                                aria-label="Edit post"
                            >
                                <Link :href="edit(row.uuid)">
                                    <PencilIcon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_POSTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend post"
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
                    <PermissionGuard v-else permission="RESTORE_POSTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore post"
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
                label="posts"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this post?"
        description="It will be soft-deleted and drop out of the public feed — you can restore it afterwards."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.post_title }}</p>
            <p class="text-muted-foreground">
                {{
                    pendingDelete.category?.blog_category_name ?? 'No category'
                }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'post' : 'posts'}?`"
        description="They will be soft-deleted and drop out of the public feed — you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="post in selectedActive"
                :key="post.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ post.post_title }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ post.category?.blog_category_name ?? '—' }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
