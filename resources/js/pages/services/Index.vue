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
import ServiceFormDialog from '@/modules/services/components/ServiceFormDialog.vue';
import { useServiceMutations } from '@/modules/services/composables/useServiceMutations';
import {
    defaultServiceFilters,
    useServices,
} from '@/modules/services/composables/useServices';
import type { Service, ServiceStatusFilter } from '@/modules/services/types';
import { index } from '@/routes/services';
import { exportMethod } from '@/routes/services/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Services', href: index() }],
    },
});

const { services, meta: queryMeta, filters, isLoading } = useServices();
const {
    deleteService,
    restoreService,
    bulkDeleteServices,
    bulkRestoreServices,
} = useServiceMutations();

const { can } = usePermissions();

// Restore search / status / date range / page / sort from the URL on load,
// and mirror every later change back with `history.replaceState`. `per_page`
// is fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultServiceFilters(),
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
    ) as ServiceStatusFilter;
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

const columns: DataTableColumn<Service>[] = [
    {
        key: 'name',
        header: 'Name',
        value: (row) => row.name,
        sortable: true,
        align: 'left',
    },
    {
        key: 'slug',
        header: 'Slug',
        value: (row) => row.slug,
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'description',
        header: 'Description',
        value: (row) => row.description,
        align: 'left',
        hideOnMobile: true,
    },
    { key: 'is_active', header: 'Status' },
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

        filters.value.sort_field =
            value.field as typeof filters.value.sort_field;
        filters.value.sort_order = value.direction === 'asc' ? 1 : -1;
    },
});

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<Service[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((service) => service.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((service) => service.deleted_at !== null),
);

function rowClass(row: Service): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingService = ref<Service | null>(null);

function openCreateDialog(): void {
    editingService.value = null;
    dialogOpen.value = true;
}

function openEditDialog(service: Service): void {
    editingService.value = service;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Service | null>(null);

function requestDelete(service: Service): void {
    pendingDelete.value = service;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError`
 * (in `useServiceMutations`) already toasts the failure, so re-throwing here
 * would only surface as an unhandled rejection with nothing left to do with
 * it — `ConfirmModal`/`DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteService.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(service: Service): Promise<void> {
    await restoreService.mutateAsync(service.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteServices.mutateAsync(
            selectedActive.value.map((service) => service.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreServices.mutateAsync(
            selectedDeleted.value.map((service) => service.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Services" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Services</h1>
            <p class="text-sm text-muted-foreground">
                Catalog entries shown in the public booking form.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search name or slug…"
                    aria-label="Search services"
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
                    :can-delete="can('BULK_DELETE_SERVICES')"
                    :can-restore="can('BULK_RESTORE_SERVICES')"
                    :busy="
                        bulkDeleteServices.isLoading.value ||
                        bulkRestoreServices.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_SERVICES">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_SERVICES">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New service
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:sort="sortModel"
                v-model:selection="selection"
                :rows="services"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Services in the catalog"
                empty-title="No services yet"
                empty-description="Create the first service to show it in the public booking form."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:is_active`]="{ row }">
                    <Badge :variant="row.is_active ? 'default' : 'secondary'">
                        {{ row.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_SERVICES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit service"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_SERVICES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete service"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_SERVICES">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore service"
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
                label="services"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ServiceFormDialog v-model:open="dialogOpen" :service="editingService" />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this service?"
        :description="`${pendingDelete?.name ?? 'This service'} will be soft-deleted. You can restore it afterwards.`"
        confirm-label="Delete"
        @confirm="confirmDelete"
    />

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        title="Delete the selected services?"
        :description="`${selectedActive.length} service(s) will be soft-deleted. You can restore them afterwards.`"
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    />
</template>
