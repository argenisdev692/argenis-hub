<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    EyeIcon,
    FileTextIcon,
    PencilIcon,
    PlusIcon,
    RotateCcwIcon,
    Trash2Icon,
    XIcon,
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
import { useClientOptions } from '@/modules/clients/composables/useClientOptions';
import InvoiceDetailDialog from '@/modules/invoices/components/InvoiceDetailDialog.vue';
import InvoiceFormDialog from '@/modules/invoices/components/InvoiceFormDialog.vue';
import InvoicePaidBadge from '@/modules/invoices/components/InvoicePaidBadge.vue';
import InvoiceStatusBadge from '@/modules/invoices/components/InvoiceStatusBadge.vue';
import { useInvoice } from '@/modules/invoices/composables/useInvoice';
import { useInvoiceMutations } from '@/modules/invoices/composables/useInvoiceMutations';
import {
    defaultInvoiceFilters,
    isInvoicePaymentFilter,
    isInvoiceStatusFilter,
    useInvoices,
} from '@/modules/invoices/composables/useInvoices';
import { buildInvoiceQueryParams } from '@/modules/invoices/helpers/buildInvoiceQueryParams';
import {
    formatDate,
    formatMoney,
    invoiceLabel,
    isOverdue,
} from '@/modules/invoices/helpers/invoicePresentation';
import type { InvoiceListItem } from '@/modules/invoices/types';
import { index as invoicesIndex } from '@/routes/invoices';
import { exportMethod, pdf } from '@/routes/invoices/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Invoices', href: invoicesIndex() }],
    },
});

const { invoices, meta: queryMeta, filters, isLoading } = useInvoices();

const {
    deleteInvoice,
    restoreInvoice,
    bulkDeleteInvoices,
    bulkRestoreInvoices,
} = useInvoiceMutations();

const { can } = usePermissions();

// Restore search / status / client / year / date range / page from the URL on
// load, and mirror every later change back with `history.replaceState`.
// `per_page` is fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultInvoiceFilters(),
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

/**
 * Every filter change funnels through here.
 *
 * Both halves matter. The list restarts at page 1 because a narrowed result set
 * rarely has the page the operator was on. And the selection is dropped because
 * a row selected under the previous filters may no longer be on screen — a
 * stale uuid in it would arm a bulk action against something the operator can
 * no longer see. Keeping it in one function is what stops the seventh filter
 * from remembering the first half and forgetting the second.
 */
function onFiltersChanged(): void {
    filters.value.page = 1;
    selection.value = [];
}

/** `DataTableSearch` debounces internally; committing a term resets the page. */
const searchTerm = computed<string>({
    get: () => filters.value.search,
    set: (value) => {
        filters.value.search = value;
        onFiltersChanged();
    },
});

const dateRange = computed<DateRange>({
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) => {
        filters.value.date_from = value.from;
        filters.value.date_to = value.to;
        onFiltersChanged();
    },
});

/**
 * The active filter set, as the export endpoint's query string wants it.
 *
 * The same helper the list query calls, so an exported spreadsheet can never
 * show a different set of rows than the table above it.
 */
const exportParams = computed(() => buildInvoiceQueryParams(filters.value));

const exportEndpoint = exportMethod.url();

/**
 * Whether anything is narrowing the list.
 *
 * Drives the empty state's wording. "No invoices yet — create the first one" is
 * actively misleading on a full ledger that six filters happen to have narrowed
 * to nothing, and with this many facets that is an easy state to reach by
 * accident. `status` and `payment_status` compare against `'all'` rather than a
 * falsy check because that is their neutral value, not `''`.
 */
const hasActiveFilters = computed<boolean>(() => {
    const active = filters.value;

    return (
        active.search !== '' ||
        active.status !== 'all' ||
        active.payment_status !== 'all' ||
        active.client_uuid !== null ||
        active.year !== null ||
        active.date_from !== null ||
        active.date_to !== null
    );
});

/**
 * Resets every facet at once.
 *
 * Six controls is enough that clearing them one at a time is a chore, and the
 * empty state tells the operator to clear a filter — so there had better be one
 * gesture that does it. Rebuilt from `defaultInvoiceFilters()` rather than
 * assigned field by field, so a filter added later is cleared by this without
 * anyone remembering to come back here.
 *
 * `per_page` is carried over instead of reset: it is the page size, not a
 * filter, and it is the one key `useUrlSyncedFilters` deliberately keeps out of
 * the URL. Every other value returns to its default, which is exactly what
 * makes the query string empty itself out again.
 */
function clearFilters(): void {
    filters.value = {
        ...defaultInvoiceFilters(),
        per_page: filters.value.per_page,
    };

    onFiltersChanged();
}

/**
 * Three options here, unlike the CVs table.
 *
 * `InvoiceFilterData` accepts `active`, `suspended` and `all`, and only `all`
 * lifts the `SoftDeletes` scope — so "All" is a real, distinct result set
 * rather than a synonym for "Active", and it is the one that puts live and
 * suspended invoices on screen together for a mixed bulk selection.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = isInvoiceStatusFilter(value) ? value : 'all';
    onFiltersChanged();
}

/**
 * The settlement axis, applied by the server rather than to the page on screen.
 *
 * Kept as its own control instead of being folded into the status select: the
 * two are orthogonal, and a single list would have to invent a name for
 * "suspended but paid".
 */
const paymentOptions: FilterSelectOption[] = [
    { value: 'all', label: 'Any payment' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'paid', label: 'Paid' },
];

function onPaymentStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.payment_status = isInvoicePaymentFilter(value)
        ? value
        : 'all';
    onFiltersChanged();
}

const { clientOptions } = useClientOptions();

const clientFilterOptions = computed<FilterSelectOption[]>(() =>
    clientOptions.value.map((client) => ({
        value: client.uuid,
        label: client.label,
    })),
);

function onClientChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.client_uuid = typeof value === 'string' ? value : null;
    onFiltersChanged();
}

/**
 * The six most recent years.
 *
 * Derived rather than fetched: `year` is `min:2000|max:2100` on the server with
 * no endpoint that enumerates the years actually in use, and an operator
 * filtering a ledger is looking at this year or the last few. A year with no
 * invoices simply returns an empty table, which is a truthful answer.
 */
const yearOptions = computed<FilterSelectOption[]>(() => {
    const current = new Date().getFullYear();

    return Array.from({ length: 6 }, (_value, offset) => {
        const year = current - offset;

        return { value: String(year), label: String(year) };
    });
});

function onYearChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.year = typeof value === 'string' ? Number(value) : null;
    onFiltersChanged();
}

const columns: DataTableColumn<InvoiceListItem>[] = [
    { key: 'invoice_number', header: 'Number', align: 'left' },
    {
        key: 'client_name',
        header: 'Client',
        align: 'left',
        value: (row) => row.client_name ?? '—',
    },
    {
        key: 'issue_date',
        header: 'Issued',
        value: (row) => formatDate(row.issue_date),
        hideOnMobile: true,
    },
    { key: 'due_date', header: 'Due', hideOnMobile: true },
    { key: 'total', header: 'Total', align: 'right' },
    { key: 'payment', header: 'Payment' },
    { key: 'status', header: 'Status', hideOnMobile: true },
];

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<InvoiceListItem[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((invoice) => invoice.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((invoice) => invoice.deleted_at !== null),
);

function rowClass(row: InvoiceListItem): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

/**
 * The row whose full record the dialogs need.
 *
 * One uuid and one `useInvoice` serve both the detail view and the edit form,
 * because they want the same request: an operator who opens an invoice and then
 * decides to edit it gets the second dialog instantly off the first one's cache.
 * `null` is create mode — `useInvoice` stays idle and the form starts empty.
 */
const activeUuid = ref<string | null>(null);

const { invoice: activeInvoice, isLoading: invoiceLoading } = useInvoice(
    () => activeUuid.value,
);

const isLoadingActiveInvoice = computed(
    () => activeUuid.value !== null && invoiceLoading.value,
);

const detailOpen = ref(false);
const formOpen = ref(false);

function openDetail(invoice: InvoiceListItem): void {
    activeUuid.value = invoice.uuid;
    detailOpen.value = true;
}

function openCreateDialog(): void {
    activeUuid.value = null;
    formOpen.value = true;
}

function openEditDialog(invoice: InvoiceListItem): void {
    activeUuid.value = invoice.uuid;
    formOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<InvoiceListItem | null>(null);

function requestDelete(invoice: InvoiceListItem): void {
    pendingDelete.value = invoice;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useInvoiceMutations`) already toasted the failure, so re-throwing here would
 * only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal`/`DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteInvoice.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(invoice: InvoiceListItem): Promise<void> {
    await restoreInvoice.mutateAsync(invoice.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteInvoices.mutateAsync(
            selectedActive.value.map((invoice) => invoice.uuid),
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
        await bulkRestoreInvoices.mutateAsync(
            selectedDeleted.value.map((invoice) => invoice.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkRestoreOpen.value = false;
}

/** The unpaid balance on screen — the number this table exists to surface. */
const outstanding = computed(() =>
    invoices.value
        .filter((invoice) => !invoice.is_paid && invoice.deleted_at === null)
        .reduce((carry, invoice) => carry + invoice.total, 0),
);

/**
 * The currency the summary is denominated in.
 *
 * A ledger can legitimately mix currencies, and summing across them would be
 * nonsense — so the line is only shown when every unpaid row on this page
 * agrees, and stays hidden otherwise rather than printing a meaningless total.
 */
const outstandingCurrency = computed<string | null>(() => {
    const currencies = new Set(
        invoices.value
            .filter(
                (invoice) => !invoice.is_paid && invoice.deleted_at === null,
            )
            .map((invoice) => invoice.currency),
    );

    return currencies.size === 1 ? [...currencies][0] : null;
});
</script>

<template>
    <Head title="Invoices" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Invoices</h1>
            <p class="text-sm text-muted-foreground">
                {{ meta.total }}
                {{ meta.total === 1 ? 'record' : 'records' }} found.
                <template v-if="outstandingCurrency && outstanding > 0">
                    ·
                    <span class="font-medium text-foreground">
                        {{ formatMoney(outstanding, outstandingCurrency) }}
                    </span>
                    outstanding on this page.
                </template>
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <!--
                    The placeholder names the two columns `scopeApplyFilters`
                    actually matches. It previously offered notes as well, which
                    the query never searched — a placeholder that promises a
                    column is a promise the operator will test.
                -->
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search by invoice number or client…"
                    aria-label="Search invoices"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Issued any time"
                />

                <FilterSelect
                    class="w-48"
                    :options="clientFilterOptions"
                    placeholder="Any client"
                    search-placeholder="Search clients…"
                    :model-value="filters.client_uuid"
                    @update:model-value="onClientChange"
                />

                <FilterSelect
                    class="w-28"
                    :options="yearOptions"
                    placeholder="Any year"
                    :model-value="
                        filters.year === null ? null : String(filters.year)
                    "
                    @update:model-value="onYearChange"
                />

                <FilterSelect
                    class="w-36"
                    :options="paymentOptions"
                    :clearable="false"
                    :model-value="filters.payment_status"
                    @update:model-value="onPaymentStatusChange"
                />

                <FilterSelect
                    class="w-36"
                    :options="statusOptions"
                    :clearable="false"
                    :model-value="filters.status"
                    @update:model-value="onStatusChange"
                />

                <!--
                    Only rendered while something is actually narrowing the
                    list: a permanently visible "Clear filters" on an unfiltered
                    table is a control that does nothing, and its presence is
                    the only signal the operator gets that filters are on.
                -->
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    @click="clearFilters"
                >
                    <XIcon class="size-4" aria-hidden="true" />
                    Clear filters
                </Button>
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_INVOICES')"
                    :can-restore="can('BULK_RESTORE_INVOICES')"
                    :busy="
                        bulkDeleteInvoices.isLoading.value ||
                        bulkRestoreInvoices.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="confirmBulkRestoreOpen = true"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_INVOICES">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_INVOICES">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New invoice
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:selection="selection"
                :rows="invoices"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Issued invoices, newest first"
                :empty-title="
                    hasActiveFilters
                        ? 'No invoices match these filters'
                        : 'No invoices yet'
                "
                :empty-description="
                    hasActiveFilters
                        ? 'Try widening the date range, or clearing the client, year or payment filter.'
                        : 'Create the first invoice to start billing a client.'
                "
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:invoice_number`]="{ row }">
                    <span class="font-medium tabular-nums">
                        {{ row.invoice_number }}
                    </span>
                </template>

                <template #[`cell:due_date`]="{ row }">
                    <span
                        :class="
                            isOverdue(row)
                                ? 'font-medium text-destructive'
                                : undefined
                        "
                    >
                        {{ formatDate(row.due_date) }}
                    </span>
                </template>

                <template #[`cell:total`]="{ row }">
                    <span class="font-medium tabular-nums">
                        {{ formatMoney(row.total, row.currency) }}
                    </span>
                </template>

                <template #[`cell:payment`]="{ row }">
                    <InvoicePaidBadge
                        :is-paid="row.is_paid"
                        :overdue="isOverdue(row)"
                    />
                </template>

                <template #[`cell:status`]="{ row }">
                    <InvoiceStatusBadge :deleted-at="row.deleted_at" />
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_INVOICES">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View invoice"
                            title="View"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <PermissionGuard permission="EXPORT_INVOICES">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="Download invoice PDF"
                            title="Download PDF"
                        >
                            <a
                                :href="pdf.url(row.uuid)"
                                target="_blank"
                                rel="noopener"
                            >
                                <FileTextIcon
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_INVOICES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit invoice"
                                title="Edit"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_INVOICES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend invoice"
                                title="Suspend"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_INVOICES">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore invoice"
                            title="Restore"
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
                label="invoices"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <InvoiceDetailDialog
        v-model:open="detailOpen"
        :invoice="activeInvoice"
        :loading="isLoadingActiveInvoice"
    />

    <InvoiceFormDialog
        v-model:open="formOpen"
        :invoice="activeInvoice"
        :loading-invoice="isLoadingActiveInvoice"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this invoice?"
        description="It will be soft-deleted and hidden from the active list — you can restore it afterwards. Its number stays reserved, so it can never be reused."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ invoiceLabel(pendingDelete) }}</p>
            <p class="text-muted-foreground">
                {{ formatMoney(pendingDelete.total, pendingDelete.currency) }}
                · issued {{ formatDate(pendingDelete.issue_date) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'invoice' : 'invoices'}?`"
        description="They will be soft-deleted and hidden from the active list — you can restore them afterwards. Their numbers stay reserved."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="invoice in selectedActive"
                :key="invoice.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ invoiceLabel(invoice) }}
                </span>
                <span class="shrink-0 text-muted-foreground tabular-nums">
                    {{ formatMoney(invoice.total, invoice.currency) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${selectedDeleted.length} ${selectedDeleted.length === 1 ? 'invoice' : 'invoices'}?`"
        description="They return to the active list."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="invoice in selectedDeleted"
                :key="invoice.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ invoiceLabel(invoice) }}
                </span>
                <span class="shrink-0 text-muted-foreground tabular-nums">
                    {{ formatMoney(invoice.total, invoice.currency) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
