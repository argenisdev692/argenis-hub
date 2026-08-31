<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    PencilIcon,
    RotateCcwIcon,
    SendIcon,
    SparklesIcon,
    Trash2Icon,
    TriangleAlertIcon,
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
import SocialMediaStatusBadge from '@/modules/social-media/components/SocialMediaStatusBadge.vue';
import {
    buildSocialMediaContentQueryParams,
    defaultSocialMediaContentFilters,
    useSocialMediaContent,
} from '@/modules/social-media/composables/useSocialMediaContent';
import { useSocialMediaContentMutations } from '@/modules/social-media/composables/useSocialMediaContentMutations';
import {
    businessGoalLabel,
    formatDate,
    funnelStageLabel,
    scoreTextClass,
    scoreTone,
    socialMediaAuthorName,
} from '@/modules/social-media/helpers/socialMediaPresentation';
import type {
    SocialMediaContentListItem,
    SocialMediaStatusFilter,
} from '@/modules/social-media/types';
import { create, edit, exportMethod, index } from '@/routes/social-media';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Social media', href: index() }],
    },
});

const {
    content,
    meta: queryMeta,
    filters,
    isLoading,
} = useSocialMediaContent();

const {
    deleteContent,
    restoreContent,
    publishContent,
    bulkDeleteContent,
    bulkRestoreContent,
} = useSocialMediaContentMutations();

const { can } = usePermissions();

// Restore search / status / date range / page from the URL on load, and mirror
// every later change back with `history.replaceState`. `per_page` is fixed, so
// it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultSocialMediaContentFilters(),
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
 * One axis for the lifecycle and the soft-delete state, because that is how the
 * backend reads it — see `SocialMediaStatusFilter`.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'generating', label: 'Generating' },
    { value: 'ready', label: 'Ready' },
    { value: 'needs_review', label: 'Needs review' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'published', label: 'Published' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as SocialMediaStatusFilter;
    filters.value.page = 1;
}

/**
 * The active filter set, as the export endpoint's query string wants it.
 * Built by the same helper the list query uses, so the download can never
 * cover a different set than the one on screen.
 */
const exportParams = computed(() =>
    buildSocialMediaContentQueryParams(filters.value),
);

const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<SocialMediaContentListItem>[] = [
    {
        key: 'topic',
        header: 'Topic',
        value: (row) => row.topic,
        align: 'left',
    },
    { key: 'status', header: 'Status' },
    {
        key: 'business_goal',
        header: 'Goal',
        value: (row) => businessGoalLabel(row.business_goal),
        hideOnMobile: true,
    },
    {
        key: 'funnel_stage',
        header: 'Funnel',
        value: (row) => funnelStageLabel(row.funnel_stage),
        hideOnMobile: true,
    },
    { key: 'overall_score_avg', header: 'Quality' },
    {
        key: 'user',
        header: 'Author',
        value: (row) => socialMediaAuthorName(row),
        hideOnMobile: true,
    },
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

const selection = ref<SocialMediaContentListItem[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((row) => row.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((row) => row.deleted_at !== null),
);

function rowClass(row: SocialMediaContentListItem): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

/**
 * A package still being generated has no copy to review and no scores to judge
 * it by, so the edit affordance stays out until the job settles.
 */
function isEditable(row: SocialMediaContentListItem): boolean {
    return row.deleted_at === null && row.status !== 'generating';
}

/** Publishing something already published, or still generating, is a no-op. */
function isPublishable(row: SocialMediaContentListItem): boolean {
    return (
        row.deleted_at === null &&
        row.status !== 'generating' &&
        row.status !== 'published'
    );
}

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<SocialMediaContentListItem | null>(null);

function requestDelete(row: SocialMediaContentListItem): void {
    pendingDelete.value = row;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError`
 * (in `useSocialMediaContentMutations`) already toasts the failure, so
 * re-throwing here would only surface as an unhandled rejection with nothing
 * left to do with it — `ConfirmModal`/`DataTable` invoke these as
 * fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteContent.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(row: SocialMediaContentListItem): Promise<void> {
    await restoreContent.mutateAsync(row.uuid).catch(() => undefined);
}

const confirmPublishOpen = ref(false);
const pendingPublish = ref<SocialMediaContentListItem | null>(null);

function requestPublish(row: SocialMediaContentListItem): void {
    pendingPublish.value = row;
    confirmPublishOpen.value = true;
}

/**
 * Publishing is confirmed rather than immediate: it is the one row action here
 * that is externally visible the moment it lands, and the public read model
 * serves `status = published` with no further gate.
 */
async function confirmPublish(): Promise<void> {
    if (!pendingPublish.value) {
        return;
    }

    try {
        await publishContent.mutateAsync(pendingPublish.value.uuid);
    } catch {
        return;
    }

    confirmPublishOpen.value = false;
    pendingPublish.value = null;
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteContent.mutateAsync(
            selectedActive.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreContent.mutateAsync(
            selectedDeleted.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Social media" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Social media</h1>
            <p class="text-sm text-muted-foreground">
                AI-generated content packages — review the copy and the quality
                scores, then schedule or publish.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search topic or headline…"
                    aria-label="Search social media content"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
                />

                <FilterSelect
                    class="w-44"
                    :options="statusOptions"
                    :clearable="false"
                    :model-value="filters.status"
                    @update:model-value="onStatusChange"
                />
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_SOCIAL_MEDIA')"
                    :can-restore="can('BULK_RESTORE_SOCIAL_MEDIA')"
                    :busy="
                        bulkDeleteContent.isLoading.value ||
                        bulkRestoreContent.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_SOCIAL_MEDIA">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['csv', 'xlsx', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_SOCIAL_MEDIA">
                    <Button as-child>
                        <Link :href="create()">
                            <SparklesIcon class="size-4" aria-hidden="true" />
                            Generate content
                        </Link>
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
                v-model:selection="selection"
                :rows="content"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="AI-generated social media content packages"
                empty-title="No content yet"
                empty-description="Generate the first package to see it here."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:topic`]="{ row }">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-medium">{{
                            row.topic
                        }}</span>
                        <TriangleAlertIcon
                            v-if="row.quality_warning"
                            class="size-4 shrink-0 text-warning"
                            aria-label="Finished below the quality threshold"
                        />
                    </div>
                </template>

                <template #[`cell:status`]="{ row }">
                    <SocialMediaStatusBadge
                        :status="row.status"
                        :deleted-at="row.deleted_at"
                    />
                </template>

                <template #[`cell:overall_score_avg`]="{ row }">
                    <span
                        v-if="row.overall_score_avg !== null"
                        class="font-semibold tabular-nums"
                        :class="
                            scoreTextClass(scoreTone(row.overall_score_avg))
                        "
                    >
                        {{ row.overall_score_avg }}
                    </span>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #actions="{ row }">
                    <template v-if="row.deleted_at === null">
                        <!-- Never Edit while a package is still generating. -->
                        <PermissionGuard
                            v-if="isEditable(row)"
                            permission="VIEW_SOCIAL_MEDIA"
                        >
                            <Button
                                as-child
                                variant="ghost"
                                size="icon"
                                aria-label="Review content"
                            >
                                <Link :href="edit(row.uuid)">
                                    <PencilIcon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard
                            v-if="isPublishable(row)"
                            permission="PUBLISH_SOCIAL_MEDIA"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Publish content"
                                @click="requestPublish(row)"
                            >
                                <SendIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_SOCIAL_MEDIA">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend content"
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
                    <PermissionGuard v-else permission="RESTORE_SOCIAL_MEDIA">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore content"
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
                label="packages"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this content package?"
        description="It will be soft-deleted — you can restore it afterwards."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.topic }}</p>
            <p class="text-muted-foreground">
                {{ businessGoalLabel(pendingDelete.business_goal) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmPublishOpen"
        title="Publish this content package?"
        description="It becomes visible on the public feed immediately."
        confirm-label="Publish"
        @confirm="confirmPublish"
    >
        <div
            v-if="pendingPublish"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingPublish.topic }}</p>
            <p class="text-muted-foreground">
                Quality
                {{ pendingPublish.overall_score_avg ?? '—' }} ·
                {{ funnelStageLabel(pendingPublish.funnel_stage) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'package' : 'packages'}?`"
        description="They will be soft-deleted — you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="row in selectedActive"
                :key="row.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ row.topic }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ funnelStageLabel(row.funnel_stage) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
