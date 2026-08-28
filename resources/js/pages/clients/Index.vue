<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
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
import ClientDetailDialog from '@/modules/clients/components/ClientDetailDialog.vue';
import ClientFormDialog from '@/modules/clients/components/ClientFormDialog.vue';
import { useClientMutations } from '@/modules/clients/composables/useClientMutations';
import {
    defaultClientFilters,
    useClients,
} from '@/modules/clients/composables/useClients';
import {
    clientStatusLabel,
    clientStatusVariant,
    formatDate,
} from '@/modules/clients/helpers/clientPresentation';
import type { Client, ClientStatusFilter } from '@/modules/clients/types';
import { index } from '@/routes/clients';
import { exportMethod } from '@/routes/clients/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clients', href: index() }],
    },
});

const { clients, meta: queryMeta, filters, isLoading } = useClients();
const { deleteClient, restoreClient, bulkDeleteClients, bulkRestoreClients } =
    useClientMutations();

const { can } = usePermissions();

// Restore search / status / date range / page / sort from the URL on load, and
// mirror every later change back with `history.replaceState`. `per_page` is
// fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultClientFilters(),
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
    ) as ClientStatusFilter;
    filters.value.page = 1;
}

const columns: DataTableColumn<Client>[] = [
    {
        key: 'client_name',
        header: 'Name',
        value: (row) => row.client_name,
        sortable: true,
        align: 'left',
    },
    {
        key: 'email',
        header: 'Email',
        value: (row) => row.email,
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'phone',
        header: 'Phone',
        value: (row) => row.phone,
        hideOnMobile: true,
    },
    {
        key: 'country',
        header: 'Country',
        value: (row) => row.country,
        hideOnMobile: true,
    },
    { key: 'status', header: 'Lifecycle', sortable: true },
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

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});

const selection = ref<Client[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((client) => client.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((client) => client.deleted_at !== null),
);

function rowClass(row: Client): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const detailOpen = ref(false);
const viewingClient = ref<Client | null>(null);

function openDetail(client: Client): void {
    viewingClient.value = client;
    detailOpen.value = true;
}

const dialogOpen = ref(false);
const editingClient = ref<Client | null>(null);

function openCreateDialog(): void {
    editingClient.value = null;
    dialogOpen.value = true;
}

function openEditDialog(client: Client): void {
    editingClient.value = client;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Client | null>(null);

function requestDelete(client: Client): void {
    pendingDelete.value = client;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useClientMutations`) already toasts the failure, so re-throwing here would
 * only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal` / `DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteClient.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(client: Client): Promise<void> {
    await restoreClient.mutateAsync(client.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteClients.mutateAsync(
            selectedActive.value.map((client) => client.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreClients.mutateAsync(
            selectedDeleted.value.map((client) => client.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Clients" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Clients</h1>
            <p class="text-sm text-muted-foreground">
                Companies and people you work with. Search, filter by lifecycle
                or date, export the current view, and manage records inline.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search name, email, tax ID or NIF…"
                    aria-label="Search clients"
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
                    :can-delete="can('BULK_DELETE_CLIENTS')"
                    :can-restore="can('BULK_RESTORE_CLIENTS')"
                    :busy="
                        bulkDeleteClients.isLoading.value ||
                        bulkRestoreClients.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_CLIENTS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_CLIENTS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New client
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
                v-model:sort="sortModel"
                v-model:selection="selection"
                :rows="clients"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="CRM clients"
                empty-title="No clients yet"
                empty-description="Create the first client to start tracking the relationship."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:status`]="{ row }">
                    <Badge :variant="clientStatusVariant(row.status)">
                        {{ clientStatusLabel(row.status) }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_CLIENTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View client"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_CLIENTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit client"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_CLIENTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete client"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_CLIENTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore client"
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
                label="clients"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ClientDetailDialog v-model:open="detailOpen" :client="viewingClient" />

    <ClientFormDialog v-model:open="dialogOpen" :client="editingClient" />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this client?"
        description="It will be soft-deleted — you can restore it afterwards."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.client_name }}</p>
            <p class="text-muted-foreground">
                {{ pendingDelete.email ?? pendingDelete.phone }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${selectedActive.length} ${selectedActive.length === 1 ? 'client' : 'clients'}?`"
        description="They will be soft-deleted — you can restore them afterwards."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="client in selectedActive"
                :key="client.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ client.client_name }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ client.email ?? client.phone }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
