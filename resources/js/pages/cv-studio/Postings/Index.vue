<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    EyeIcon,
    FilterXIcon,
    PlayIcon,
    RotateCcwIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import type { FilterSelectOption } from '@/common/form';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import { cn } from '@/lib/utils';
import FitScoreBadge from '@/modules/cv-studio/components/FitScoreBadge.vue';
import ManualReferencesPanel from '@/modules/cv-studio/components/ManualReferencesPanel.vue';
import PasteJobTextDialog from '@/modules/cv-studio/components/PasteJobTextDialog.vue';
import PostingRowActions from '@/modules/cv-studio/components/PostingRowActions.vue';
import PostingStatusBadge from '@/modules/cv-studio/components/PostingStatusBadge.vue';
import { usePostingMutations } from '@/modules/cv-studio/composables/usePostingMutations';
import {
    defaultPostingFilters,
    POSTINGS_PER_PAGE_OPTIONS,
    usePostings,
} from '@/modules/cv-studio/composables/usePostings';
import { useReferences } from '@/modules/cv-studio/composables/useReferences';
import { buildPostingQueryParams } from '@/modules/cv-studio/helpers/buildPostingQueryParams';
import {
    formatDate,
    POSTING_STAGE_OPTIONS,
    REMOTE_SCOPE_OPTIONS,
    remoteScopeLabel,
} from '@/modules/cv-studio/helpers/studioPresentation';
import type {
    StudioPosting,
    StudioPostingSortField,
    StudioPostingStage,
    StudioReference,
    StudioStatusFilter,
} from '@/modules/cv-studio/types';
import { index as studioIndex } from '@/routes/cv-studio';
import { exportMethod, index, show } from '@/routes/cv-studio/postings';
import { store as storeRun } from '@/routes/cv-studio/runs';

type PostingsTab = 'postings' | 'manual';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: studioIndex() },
            { title: 'Postings', href: index() },
        ],
    },
});

/** `/cv-studio/references` renders this page with `tab: 'manual'`. */
const { tab: initialTab = 'postings' } = defineProps<{
    tab?: PostingsTab;
}>();

const tab = ref<PostingsTab>(initialTab);

const { postings, meta, filters, isPending, isLoading } = usePostings();
const {
    deletePosting,
    restorePosting,
    bulkDeletePostings,
    bulkRestorePostings,
    updatePostingStage,
} = usePostingMutations();
const { meta: referencesMeta } = useReferences();

const { can } = usePermissions();
const selection = ref<StudioPosting[]>([]);

/** Search, facets, sort, page and page size all survive a reload or a shared link. */
useUrlSyncedFilters(filters, { defaults: defaultPostingFilters() });

/** Every filter change resets paging and drops a selection that may no longer be visible. */
function applyFilter(apply: () => void): void {
    apply();
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

const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'all', label: 'All' },
];

type FilterSelectModel =
    FilterSelectOption['value'] | FilterSelectOption['value'][] | null;

function isStatusFilter(value: unknown): value is StudioStatusFilter {
    return statusOptions.some((option) => option.value === value);
}

function isStage(value: unknown): value is StudioPostingStage {
    return POSTING_STAGE_OPTIONS.some((option) => option.value === value);
}

const scopeModel = computed<FilterSelectModel>({
    get: () => filters.value.remote_scope || null,
    set: (value) =>
        applyFilter(() => {
            filters.value.remote_scope =
                REMOTE_SCOPE_OPTIONS.find((option) => option.value === value)
                    ?.value ?? '';
        }),
});

const stagesModel = computed<FilterSelectModel>({
    get: () => filters.value.stages,
    set: (value) =>
        applyFilter(() => {
            filters.value.stages = (Array.isArray(value) ? value : []).filter(
                isStage,
            );
        }),
});

const statusModel = computed<FilterSelectModel>({
    get: () => filters.value.status,
    set: (value) =>
        applyFilter(() => {
            filters.value.status = isStatusFilter(value) ? value : 'active';
        }),
});

const SORTABLE_FIELDS: readonly StudioPostingSortField[] = [
    'created_at',
    'title',
    'employer_name',
    'fit',
];

const sortModel = computed<DataTableSort | null>({
    get: (): DataTableSort => ({
        field: filters.value.sort_field,
        direction: filters.value.sort_order === 1 ? 'asc' : 'desc',
    }),
    set: (value: DataTableSort | null) => {
        const field = SORTABLE_FIELDS.find((key) => key === value?.field);

        applyFilter(() => {
            filters.value.sort_field = field ?? 'created_at';
            filters.value.sort_order = value?.direction === 'asc' ? 1 : -1;
        });
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const perPage = computed<number>({
    get: () => filters.value.per_page,
    set: (value) => applyFilter(() => (filters.value.per_page = value)),
});

/** True once anything narrows the list — drives "Clear filters" and the empty-state copy. */
const hasActiveFilters = computed(() => {
    const current = filters.value;

    return (
        current.search !== '' ||
        current.remote_scope !== '' ||
        current.stages.length > 0 ||
        current.status !== 'active' ||
        current.date_from !== null ||
        current.date_to !== null
    );
});

function clearFilters(): void {
    const { sort_field, sort_order, per_page } = filters.value;

    filters.value = {
        ...defaultPostingFilters(),
        sort_field,
        sort_order,
        per_page,
    };
    selection.value = [];
}

const exportParams = computed(() => buildPostingQueryParams(filters.value));
const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<StudioPosting>[] = [
    { key: 'title', header: 'Posting', align: 'left', sortable: true },
    {
        key: 'employer_name',
        header: 'Employer',
        sortable: true,
        hideOnMobile: true,
    },
    { key: 'scope', header: 'Scope', hideOnMobile: true },
    { key: 'fit', header: 'Fit', sortable: true },
    { key: 'status', header: 'Stage' },
    {
        key: 'created_at',
        header: 'Found',
        value: (row) => formatDate(row.created_at),
        sortable: true,
        hideOnMobile: true,
    },
];

/** `--deleted-row-*` tokens via `.data-row-deleted` (app.css) — theme-aware. */
function rowClass(row: StudioPosting): string | undefined {
    return row.deleted_at ? 'data-row-deleted' : undefined;
}

const selectedActive = computed(() =>
    selection.value.filter((posting) => posting.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((posting) => posting.deleted_at !== null),
);

function pluralPostings(count: number): string {
    return `${count} ${count === 1 ? 'posting' : 'postings'}`;
}

const pendingDelete = ref<StudioPosting | null>(null);
const pendingRestore = ref<StudioPosting | null>(null);
const confirmBulkDeleteOpen = ref(false);
const confirmBulkRestoreOpen = ref(false);

/** A two-way `open` model over a "pending row" ref, so each modal needs one ref, not two. */
function openWhilePending(pending: typeof pendingDelete) {
    return computed<boolean>({
        get: () => pending.value !== null,
        set: (open) => {
            if (!open) {
                pending.value = null;
            }
        },
    });
}

const confirmDeleteOpen = openWhilePending(pendingDelete);
const confirmRestoreOpen = openWhilePending(pendingRestore);

/**
 * `ConfirmModal` stays busy while these await. A failure keeps the modal open
 * (the mutation already toasted why) so the user can retry or cancel.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deletePosting.mutateAsync(pendingDelete.value.uuid);
        pendingDelete.value = null;
    } catch {
        // Toasted by the mutation; the modal stays open for a retry.
    }
}

async function confirmRestore(): Promise<void> {
    if (!pendingRestore.value) {
        return;
    }

    try {
        await restorePosting.mutateAsync(pendingRestore.value.uuid);
        pendingRestore.value = null;
    } catch {
        // Toasted by the mutation; the modal stays open for a retry.
    }
}

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeletePostings.mutateAsync(
            selectedActive.value.map((posting) => posting.uuid),
        );
        selection.value = [];
        confirmBulkDeleteOpen.value = false;
    } catch {
        // Toasted by the mutation; the modal stays open for a retry.
    }
}

async function confirmBulkRestore(): Promise<void> {
    try {
        await bulkRestorePostings.mutateAsync(
            selectedDeleted.value.map((posting) => posting.uuid),
        );
        selection.value = [];
        confirmBulkRestoreOpen.value = false;
    } catch {
        // Toasted by the mutation; the modal stays open for a retry.
    }
}

function changeStage(posting: StudioPosting, stage: StudioPostingStage): void {
    updatePostingStage.mutate({ uuid: posting.uuid, stage });
}

const pasteOpen = ref(false);
const pasteTarget = ref<StudioReference | null>(null);

function openPasteDialog(reference: StudioReference): void {
    pasteTarget.value = reference;
    pasteOpen.value = true;
}

const startingRun = ref(false);

function startRun(): void {
    router.post(storeRun.url(), undefined, {
        onStart: () => (startingRun.value = true),
        onFinish: () => (startingRun.value = false),
    });
}
</script>

<template>
    <Head title="CV Studio · Postings" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Postings</h1>
                <p class="max-w-prose text-sm text-muted-foreground">
                    Fit and opportunity stay separate — the threshold decides
                    entry, the product decides order.
                </p>
            </div>

            <PermissionGuard permission="CREATE_STUDIO_POSTINGS">
                <Button :disabled="startingRun" @click="startRun">
                    <PlayIcon class="size-4" aria-hidden="true" />
                    {{ startingRun ? 'Starting…' : 'Start discovery run' }}
                </Button>
            </PermissionGuard>
        </header>

        <Tabs v-model="tab" class="gap-4">
            <TabsList aria-label="Posting sources" class="w-full sm:w-fit">
                <TabsTrigger value="postings">
                    Scored postings
                    <span class="text-muted-foreground tabular-nums">
                        {{ meta.total }}
                    </span>
                </TabsTrigger>
                <TabsTrigger value="manual">
                    Open manually
                    <span class="text-muted-foreground tabular-nums">
                        {{ referencesMeta.total }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="postings" class="flex flex-col gap-4">
                <DataTableToolbar
                    :selected-count="selection.length"
                    selection-label="selected"
                    aria-label="Filter postings"
                >
                    <template #search>
                        <DataTableSearch
                            v-model="searchTerm"
                            placeholder="Search title, employer or location…"
                            aria-label="Search postings"
                        />
                    </template>

                    <template #filters>
                        <FilterSelect
                            v-model="stagesModel"
                            class="w-full sm:w-48"
                            :options="POSTING_STAGE_OPTIONS"
                            multiple
                            placeholder="Any stage"
                            :max-visible-chips="1"
                        />

                        <FilterSelect
                            v-model="scopeModel"
                            class="w-full sm:w-44"
                            :options="REMOTE_SCOPE_OPTIONS"
                            placeholder="Any scope"
                        />

                        <DataTableDateRangeFilter
                            v-model="dateRange"
                            placeholder="Found any time"
                            presets
                            disable-future
                        />

                        <FilterSelect
                            v-model="statusModel"
                            class="w-full sm:w-36"
                            :options="statusOptions"
                            :clearable="false"
                        />

                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <FilterXIcon class="size-4" aria-hidden="true" />
                            Clear filters
                        </Button>
                    </template>

                    <template #bulk>
                        <DataTableBulkActions
                            :selection="selection"
                            :can-delete="can('BULK_DELETE_STUDIO_POSTINGS')"
                            :can-restore="can('BULK_RESTORE_STUDIO_POSTINGS')"
                            :busy="
                                bulkDeletePostings.isLoading.value ||
                                bulkRestorePostings.isLoading.value
                            "
                            @bulk-delete="confirmBulkDeleteOpen = true"
                            @bulk-restore="confirmBulkRestoreOpen = true"
                        />
                    </template>

                    <template #actions>
                        <PermissionGuard permission="EXPORT_STUDIO_POSTINGS">
                            <DataTableExportMenu
                                :endpoint="exportEndpoint"
                                :params="exportParams"
                                :formats="['xlsx', 'csv', 'pdf']"
                                :disabled="meta.total === 0"
                            />
                        </PermissionGuard>
                    </template>
                </DataTableToolbar>

                <div class="flex flex-col">
                    <DataTable
                        v-model:selection="selection"
                        v-model:sort="sortModel"
                        :rows="postings"
                        :columns="columns"
                        :loading="isPending"
                        :row-class="rowClass"
                        selectable
                        caption="Discovered postings with fit scores"
                        :empty-title="
                            hasActiveFilters
                                ? 'No postings match these filters'
                                : 'No postings yet'
                        "
                        :empty-description="
                            hasActiveFilters
                                ? 'Widen the search or clear the filters to see more.'
                                : 'Start a discovery run to collect remote postings.'
                        "
                        :class="
                            cn(
                                'rounded-b-none border-b-0 transition-opacity',
                                isLoading && !isPending && 'opacity-60',
                            )
                        "
                    >
                        <template #[`cell:title`]="{ row }">
                            <div class="flex min-w-0 flex-col text-left">
                                <Link
                                    v-if="can('VIEW_STUDIO_POSTINGS')"
                                    :href="show.url(row.uuid)"
                                    class="truncate font-medium hover:underline"
                                    prefetch
                                >
                                    {{ row.title }}
                                </Link>
                                <span v-else class="truncate font-medium">
                                    {{ row.title }}
                                </span>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    <span class="md:hidden">
                                        {{
                                            row.employer_name ??
                                            'Unknown employer'
                                        }}
                                        ·
                                    </span>
                                    {{
                                        row.location_text ??
                                        remoteScopeLabel(row.remote_scope)
                                    }}
                                </span>
                            </div>
                        </template>

                        <template #[`cell:employer_name`]="{ row }">
                            {{ row.employer_name ?? '—' }}
                        </template>

                        <template #[`cell:scope`]="{ row }">
                            {{ remoteScopeLabel(row.remote_scope) }}
                        </template>

                        <template #[`cell:fit`]="{ row }">
                            <FitScoreBadge
                                :score="row.total_score"
                                :band="row.band"
                            />
                        </template>

                        <template #[`cell:status`]="{ row }">
                            <PostingStatusBadge
                                :status="row.status"
                                :deleted-at="row.deleted_at"
                            />
                        </template>

                        <template #actions="{ row }">
                            <PermissionGuard permission="VIEW_STUDIO_POSTINGS">
                                <DataTableRowAction
                                    :icon="EyeIcon"
                                    :label="`View ${row.title}`"
                                    tooltip="View match report"
                                    :href="show.url(row.uuid)"
                                    prefetch
                                />
                            </PermissionGuard>

                            <template v-if="!row.deleted_at">
                                <PermissionGuard
                                    permission="DELETE_STUDIO_POSTINGS"
                                >
                                    <DataTableRowAction
                                        :icon="Trash2Icon"
                                        :label="`Suspend ${row.title}`"
                                        tooltip="Suspend"
                                        destructive
                                        @click="pendingDelete = row"
                                    />
                                </PermissionGuard>

                                <PostingRowActions
                                    :posting="row"
                                    :can-update="can('UPDATE_STUDIO_POSTINGS')"
                                    :busy="updatePostingStage.isLoading.value"
                                    @change-stage="changeStage(row, $event)"
                                />
                            </template>

                            <PermissionGuard
                                v-else
                                permission="RESTORE_STUDIO_POSTINGS"
                            >
                                <DataTableRowAction
                                    :icon="RotateCcwIcon"
                                    :label="`Restore ${row.title}`"
                                    tooltip="Restore"
                                    @click="pendingRestore = row"
                                />
                            </PermissionGuard>
                        </template>
                    </DataTable>

                    <Paginator
                        v-model:page="page"
                        v-model:per-page="perPage"
                        :per-page-options="POSTINGS_PER_PAGE_OPTIONS"
                        :meta="meta"
                        :disabled="isLoading"
                        label="postings"
                        class="rounded-b-xl border border-border bg-card"
                    />
                </div>
            </TabsContent>

            <TabsContent value="manual">
                <ManualReferencesPanel @paste="openPasteDialog" />
            </TabsContent>
        </Tabs>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        :title="`Suspend “${pendingDelete?.title ?? 'this posting'}”?`"
        description="It leaves the apply list but stays in market statistics. You can restore it later."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    />

    <ConfirmModal
        v-model:open="confirmRestoreOpen"
        :title="`Restore “${pendingRestore?.title ?? 'this posting'}”?`"
        description="It returns to the apply list."
        confirm-label="Restore"
        @confirm="confirmRestore"
    />

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${pluralPostings(selectedActive.length)}?`"
        description="They leave the apply list but stay in market statistics."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    />

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${pluralPostings(selectedDeleted.length)}?`"
        description="They return to the apply list."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    />

    <PasteJobTextDialog v-model:open="pasteOpen" :reference="pasteTarget" />
</template>
