<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PencilIcon, PlusIcon, RotateCcwIcon, Trash2Icon } from '@lucide/vue';
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
import PortfolioFormSheet from '@/modules/portfolios/components/PortfolioFormSheet.vue';
import { usePortfolioMutations } from '@/modules/portfolios/composables/usePortfolioMutations';
import {
    defaultPortfolioFilters,
    usePortfolios,
} from '@/modules/portfolios/composables/usePortfolios';
import type {
    Portfolio,
    PortfolioSortField,
    PortfolioStatusFilter,
} from '@/modules/portfolios/types';
import { index } from '@/routes/portfolios';
import { exportMethod } from '@/routes/portfolios/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Portfolio', href: index() }],
    },
});

const { portfolios, meta: queryMeta, filters, isLoading } = usePortfolios();
const {
    deletePortfolio,
    restorePortfolio,
    bulkDeletePortfolios,
    bulkRestorePortfolios,
} = usePortfolioMutations();

const { can } = usePermissions();

// Restore search / status / date range / page / sort from the URL on load, and
// mirror every later change back with `history.replaceState`. `per_page` is
// fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultPortfolioFilters(),
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
    get: () => ({
        from: filters.value.date_from,
        to: filters.value.date_to,
    }),
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
    { value: 'active', label: 'Active' },
    { value: 'deleted', label: 'Deleted' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as PortfolioStatusFilter;
    filters.value.page = 1;
}

function formatDate(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

const columns: DataTableColumn<Portfolio>[] = [
    {
        key: 'title',
        header: 'Title',
        value: (row) => row.title,
        sortable: true,
        align: 'left',
    },
    {
        key: 'client_name',
        header: 'Client',
        value: (row) => row.client_name,
        sortable: true,
        align: 'left',
    },
    {
        key: 'project_type',
        header: 'Type',
        value: (row) => row.project_type,
        align: 'left',
        hideOnMobile: true,
    },
    { key: 'tech_stack', header: 'Tech', align: 'left', hideOnMobile: true },
    { key: 'is_public', header: 'Visibility' },
    {
        key: 'published_at',
        header: 'Published',
        sortable: true,
        hideOnMobile: true,
    },
    {
        key: 'sort_order',
        header: 'Order',
        value: (row) => row.sort_order,
        sortable: true,
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

        filters.value.sort_field = value.field as PortfolioSortField;
        filters.value.sort_order = value.direction === 'asc' ? 1 : -1;
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<Portfolio[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((portfolio) => portfolio.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((portfolio) => portfolio.deleted_at !== null),
);

function rowClass(row: Portfolio): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const sheetOpen = ref(false);
const editingPortfolio = ref<Portfolio | null>(null);

function openCreateSheet(): void {
    editingPortfolio.value = null;
    sheetOpen.value = true;
}

function openEditSheet(portfolio: Portfolio): void {
    editingPortfolio.value = portfolio;
    sheetOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Portfolio | null>(null);

function requestDelete(portfolio: Portfolio): void {
    pendingDelete.value = portfolio;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `usePortfolioMutations`) already toasts the failure, so re-throwing here would
 * only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal` / `DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deletePortfolio.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(portfolio: Portfolio): Promise<void> {
    await restorePortfolio.mutateAsync(portfolio.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeletePortfolios.mutateAsync(
            selectedActive.value.map((portfolio) => portfolio.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestorePortfolios.mutateAsync(
            selectedDeleted.value.map((portfolio) => portfolio.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Portfolio" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Portfolio</h1>
            <p class="text-sm text-muted-foreground">
                Showcase projects. Public, published entries feed the landing
                page and the public REST endpoint.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search title, client or type…"
                    aria-label="Search portfolios"
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
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_PORTFOLIOS')"
                    :can-restore="can('BULK_RESTORE_PORTFOLIOS')"
                    :busy="
                        bulkDeletePortfolios.isLoading.value ||
                        bulkRestorePortfolios.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_PORTFOLIOS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_PORTFOLIOS">
                    <Button @click="openCreateSheet">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New portfolio
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:sort="sortModel"
                v-model:selection="selection"
                :rows="portfolios"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Portfolio showcase projects"
                empty-title="No portfolios yet"
                empty-description="Create the first project to show it on the landing page."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:tech_stack`]="{ row }">
                    <div
                        v-if="row.tech_stack.length"
                        class="flex flex-wrap gap-1"
                    >
                        <Badge
                            v-for="tech in row.tech_stack.slice(0, 4)"
                            :key="tech"
                            variant="secondary"
                        >
                            {{ tech }}
                        </Badge>
                        <span
                            v-if="row.tech_stack.length > 4"
                            class="text-xs text-muted-foreground"
                        >
                            +{{ row.tech_stack.length - 4 }}
                        </span>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #[`cell:is_public`]="{ row }">
                    <Badge :variant="row.is_public ? 'default' : 'secondary'">
                        {{ row.is_public ? 'Public' : 'Private' }}
                    </Badge>
                </template>

                <template #[`cell:published_at`]="{ row }">
                    <span v-if="row.published_at">
                        {{ formatDate(row.published_at) }}
                    </span>
                    <Badge v-else variant="outline">Draft</Badge>
                </template>

                <template #actions="{ row }">
                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_PORTFOLIOS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit portfolio"
                                @click="openEditSheet(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_PORTFOLIOS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete portfolio"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_PORTFOLIOS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore portfolio"
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
                label="portfolios"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <PortfolioFormSheet
        v-model:open="sheetOpen"
        :portfolio="editingPortfolio"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this portfolio?"
        description="It will be soft-deleted — you can restore it afterwards."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.title }}</p>
            <p class="text-muted-foreground">
                {{ pendingDelete.client_name }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${selectedActive.length} ${selectedActive.length === 1 ? 'portfolio' : 'portfolios'}?`"
        description="They will be soft-deleted — you can restore them afterwards."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="portfolio in selectedActive"
                :key="portfolio.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ portfolio.title }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ portfolio.client_name }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
