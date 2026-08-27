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
import ContactSupportDetailDialog from '@/modules/contact-support/components/ContactSupportDetailDialog.vue';
import ContactSupportFormDialog from '@/modules/contact-support/components/ContactSupportFormDialog.vue';
import { useContactSupportMutations } from '@/modules/contact-support/composables/useContactSupportMutations';
import {
    defaultContactSupportFilters,
    useContactSupports,
} from '@/modules/contact-support/composables/useContactSupports';
import {
    contactName,
    formatDate,
} from '@/modules/contact-support/helpers/contactSupportPresentation';
import type {
    ContactSupport,
    ContactSupportStatusFilter,
    ReadStateFilter,
    SpamStateFilter,
} from '@/modules/contact-support/types';
import { index } from '@/routes/contact-supports';
import { exportMethod } from '@/routes/contact-supports/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Support inbox', href: index() }],
    },
});

const {
    contactSupports,
    meta: queryMeta,
    filters,
    isLoading,
} = useContactSupports();
const {
    deleteContactSupport,
    restoreContactSupport,
    bulkDeleteContactSupports,
    bulkRestoreContactSupports,
} = useContactSupportMutations();

const { can } = usePermissions();

// Restore search / status / read / spam / date range / page / sort from the URL
// on load, and mirror every later change back with `history.replaceState`.
// `per_page` is fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultContactSupportFilters(),
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
    readed:
        filters.value.readed === 'all'
            ? undefined
            : filters.value.readed === 'read',
    is_spam:
        filters.value.is_spam === 'all'
            ? undefined
            : filters.value.is_spam === 'spam',
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
    sort_field: filters.value.sort_field,
    sort_order: filters.value.sort_order,
}));

const exportEndpoint = exportMethod.url();

const statusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'deleted', label: 'Deleted' },
];

const readOptions: FilterSelectOption[] = [
    { value: 'all', label: 'Read & unread' },
    { value: 'unread', label: 'Unread' },
    { value: 'read', label: 'Read' },
];

const spamOptions: FilterSelectOption[] = [
    { value: 'all', label: 'Spam & ham' },
    { value: 'ham', label: 'Not spam' },
    { value: 'spam', label: 'Spam' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as ContactSupportStatusFilter;
    filters.value.page = 1;
}

function onReadChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.readed = (
        typeof value === 'string' ? value : 'all'
    ) as ReadStateFilter;
    filters.value.page = 1;
}

function onSpamChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.is_spam = (
        typeof value === 'string' ? value : 'all'
    ) as SpamStateFilter;
    filters.value.page = 1;
}

const columns: DataTableColumn<ContactSupport>[] = [
    {
        key: 'name',
        header: 'From',
        value: (row) => contactName(row),
        align: 'left',
    },
    {
        key: 'email',
        header: 'Email',
        value: (row) => row.email,
        sortable: true,
        align: 'left',
        hideOnMobile: true,
    },
    {
        key: 'subject',
        header: 'Subject',
        value: (row) => row.subject,
        sortable: true,
        align: 'left',
    },
    { key: 'readed', header: 'Read' },
    { key: 'is_spam', header: 'Spam' },
    {
        key: 'created_at',
        header: 'Received',
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

const selection = ref<ContactSupport[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((support) => support.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((support) => support.deleted_at !== null),
);

function rowClass(row: ContactSupport): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const detailOpen = ref(false);
const viewingSupport = ref<ContactSupport | null>(null);

function openDetail(support: ContactSupport): void {
    viewingSupport.value = support;
    detailOpen.value = true;
}

const dialogOpen = ref(false);
const editingSupport = ref<ContactSupport | null>(null);

function openCreateDialog(): void {
    editingSupport.value = null;
    dialogOpen.value = true;
}

function openEditDialog(support: ContactSupport): void {
    editingSupport.value = support;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<ContactSupport | null>(null);

function requestDelete(support: ContactSupport): void {
    pendingDelete.value = support;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useContactSupportMutations`) already toasts the failure, so re-throwing here
 * would only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal` / `DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteContactSupport.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(support: ContactSupport): Promise<void> {
    await restoreContactSupport
        .mutateAsync(support.uuid)
        .catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteContactSupports.mutateAsync(
            selectedActive.value.map((support) => support.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreContactSupports.mutateAsync(
            selectedDeleted.value.map((support) => support.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Support inbox" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Support inbox</h1>
            <p class="text-sm text-muted-foreground">
                Requests submitted through the public contact form. Triage the
                read and spam flags, reply out of band, and archive what is
                handled.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search name, email or subject…"
                    aria-label="Search support requests"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Received any time"
                />

                <FilterSelect
                    class="w-40"
                    :options="readOptions"
                    :clearable="false"
                    :model-value="filters.readed"
                    @update:model-value="onReadChange"
                />

                <FilterSelect
                    class="w-40"
                    :options="spamOptions"
                    :clearable="false"
                    :model-value="filters.is_spam"
                    @update:model-value="onSpamChange"
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
                    :can-delete="can('BULK_DELETE_CONTACT_SUPPORTS')"
                    :can-restore="can('BULK_RESTORE_CONTACT_SUPPORTS')"
                    :busy="
                        bulkDeleteContactSupports.isLoading.value ||
                        bulkRestoreContactSupports.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_CONTACT_SUPPORTS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['csv', 'xlsx', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_CONTACT_SUPPORTS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New request
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
                :rows="contactSupports"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Inbound support requests"
                empty-title="No support requests"
                empty-description="Requests submitted through the public contact form land here."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:readed`]="{ row }">
                    <Badge :variant="row.readed ? 'secondary' : 'default'">
                        {{ row.readed ? 'Read' : 'Unread' }}
                    </Badge>
                </template>

                <template #[`cell:is_spam`]="{ row }">
                    <Badge :variant="row.is_spam ? 'destructive' : 'outline'">
                        {{ row.is_spam ? 'Spam' : 'Ham' }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_CONTACT_SUPPORTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View support request"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_CONTACT_SUPPORTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit support request"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_CONTACT_SUPPORTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete support request"
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
                        permission="RESTORE_CONTACT_SUPPORTS"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore support request"
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
                label="support requests"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ContactSupportDetailDialog
        v-model:open="detailOpen"
        :support="viewingSupport"
    />

    <ContactSupportFormDialog
        v-model:open="dialogOpen"
        :support="editingSupport"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this support request?"
        description="It will be soft-deleted — you can restore it afterwards."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ contactName(pendingDelete) }}</p>
            <p class="text-muted-foreground">{{ pendingDelete.subject }}</p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${selectedActive.length} ${selectedActive.length === 1 ? 'request' : 'requests'}?`"
        description="They will be soft-deleted — you can restore them afterwards."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="support in selectedActive"
                :key="support.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ contactName(support) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ support.subject }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
