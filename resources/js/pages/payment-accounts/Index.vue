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
import PaymentAccountDetailDialog from '@/modules/payment-accounts/components/PaymentAccountDetailDialog.vue';
import PaymentAccountFormDialog from '@/modules/payment-accounts/components/PaymentAccountFormDialog.vue';
import { usePaymentAccountMutations } from '@/modules/payment-accounts/composables/usePaymentAccountMutations';
import {
    defaultPaymentAccountFilters,
    usePaymentAccounts,
} from '@/modules/payment-accounts/composables/usePaymentAccounts';
import {
    currencyLabel,
    formatDate,
    maskedIdentifier,
    paymentMethodLabel,
    paymentMethodVariant,
} from '@/modules/payment-accounts/helpers/paymentAccountPresentation';
import { PAYMENT_METHOD_VALUES } from '@/modules/payment-accounts/schemas/paymentAccountFormSchema';
import type {
    PaymentAccount,
    PaymentAccountStatusFilter,
    PaymentMethod,
} from '@/modules/payment-accounts/types';
import { index } from '@/routes/payment-accounts';
import { exportMethod } from '@/routes/payment-accounts/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payment accounts', href: index() }],
    },
});

const { accounts, meta: queryMeta, filters, isLoading } = usePaymentAccounts();
const {
    deleteAccount,
    restoreAccount,
    bulkDeleteAccounts,
    bulkRestoreAccounts,
} = usePaymentAccountMutations();

const { can } = usePermissions();

useUrlSyncedFilters(filters, {
    defaults: defaultPaymentAccountFilters(),
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
    status: filters.value.status === 'all' ? undefined : filters.value.status,
    method: filters.value.method ?? undefined,
    currency: filters.value.currency ?? undefined,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
    sort_field: filters.value.sort_field,
    sort_order: filters.value.sort_order,
}));

const exportEndpoint = exportMethod.url();

const rowStatusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'deleted', label: 'Deleted' },
];

const methodOptions: FilterSelectOption[] = PAYMENT_METHOD_VALUES.map(
    (method) => ({ value: method, label: paymentMethodLabel(method) }),
);

/**
 * The currencies actually in use, derived from the loaded page rather than
 * hardcoded: this is a solo-dev CRM, and the real answer is "EUR and USD"
 * today and something else the day a client pays in GBP.
 */
const currencyOptions = computed<FilterSelectOption[]>(() => {
    const seen = new Set<string>();

    for (const account of accounts.value) {
        if (account.currency) {
            seen.add(account.currency);
        }
    }

    return [...seen]
        .sort()
        .map((currency) => ({ value: currency, label: currency }));
});

function onRowStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as PaymentAccountStatusFilter;
    filters.value.page = 1;
}

function onMethodChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.method =
        typeof value === 'string' ? (value as PaymentMethod) : null;
    filters.value.page = 1;
}

function onCurrencyChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.currency = typeof value === 'string' ? value : null;
    filters.value.page = 1;
}

const columns: DataTableColumn<PaymentAccount>[] = [
    {
        key: 'label',
        header: 'Label',
        value: (row) => row.label,
        sortable: true,
        align: 'left',
    },
    { key: 'method', header: 'Method', sortable: true },
    { key: 'currency', header: 'Currency', sortable: true },
    {
        key: 'beneficiary',
        header: 'Beneficiary',
        value: (row) => row.beneficiary,
        align: 'left',
        hideOnMobile: true,
    },
    { key: 'account', header: 'Account', hideOnMobile: true },
    { key: 'flags', header: 'Flags' },
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

const selection = ref<PaymentAccount[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((account) => account.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((account) => account.deleted_at !== null),
);

function rowClass(row: PaymentAccount): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const detailOpen = ref(false);
const viewingAccount = ref<PaymentAccount | null>(null);

function openDetail(account: PaymentAccount): void {
    viewingAccount.value = account;
    detailOpen.value = true;
}

const dialogOpen = ref(false);
const editingAccount = ref<PaymentAccount | null>(null);

function openCreateDialog(): void {
    editingAccount.value = null;
    dialogOpen.value = true;
}

function openEditDialog(account: PaymentAccount): void {
    editingAccount.value = account;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<PaymentAccount | null>(null);

function requestDelete(account: PaymentAccount): void {
    pendingDelete.value = account;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError`
 * already toasts the failure, and `ConfirmModal` / `DataTable` invoke these as
 * fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteAccount.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(account: PaymentAccount): Promise<void> {
    await restoreAccount.mutateAsync(account.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteAccounts.mutateAsync(
            selectedActive.value.map((account) => account.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreAccounts.mutateAsync(
            selectedDeleted.value.map((account) => account.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Payment accounts" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Payment accounts
            </h1>
            <p class="text-sm text-muted-foreground">
                How clients pay you. Method and currency are independent, so
                Remitly&nbsp;USD, bank&nbsp;transfer&nbsp;EUR and
                bank&nbsp;transfer&nbsp;USD all coexist. Each invoice copies the
                chosen rail at issue time, so editing one here never rewrites an
                invoice you already sent.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search label, beneficiary or bank…"
                    aria-label="Search payment accounts"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
                />

                <FilterSelect
                    class="w-40"
                    placeholder="Any method"
                    :options="methodOptions"
                    :model-value="filters.method"
                    @update:model-value="onMethodChange"
                />

                <FilterSelect
                    v-if="currencyOptions.length > 0"
                    class="w-32"
                    placeholder="Any currency"
                    :options="currencyOptions"
                    :model-value="filters.currency"
                    @update:model-value="onCurrencyChange"
                />

                <FilterSelect
                    class="w-36"
                    :options="rowStatusOptions"
                    :clearable="false"
                    :model-value="filters.status"
                    @update:model-value="onRowStatusChange"
                />
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_PAYMENT_ACCOUNTS')"
                    :can-restore="can('BULK_RESTORE_PAYMENT_ACCOUNTS')"
                    :busy="
                        bulkDeleteAccounts.isLoading.value ||
                        bulkRestoreAccounts.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_PAYMENT_ACCOUNTS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_PAYMENT_ACCOUNTS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New account
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
                :rows="accounts"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Settlement rails"
                empty-title="No payment accounts yet"
                empty-description="Add the rail you get paid on so invoices can print it."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:method`]="{ row }">
                    <Badge :variant="paymentMethodVariant(row.method)">
                        {{ paymentMethodLabel(row.method) }}
                    </Badge>
                </template>

                <template #[`cell:currency`]="{ row }">
                    <span :class="row.currency ? undefined : 'text-muted-foreground'">
                        {{ currencyLabel(row.currency) }}
                    </span>
                </template>

                <template #[`cell:account`]="{ row }">
                    <span class="font-mono text-xs tabular-nums">
                        {{ maskedIdentifier(row) }}
                    </span>
                </template>

                <template #[`cell:flags`]="{ row }">
                    <div class="flex flex-wrap items-center justify-center gap-1">
                        <Badge v-if="row.is_default" variant="secondary">
                            Default
                        </Badge>
                        <Badge v-if="!row.is_active" variant="outline">
                            Inactive
                        </Badge>
                    </div>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_PAYMENT_ACCOUNTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View payment account"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_PAYMENT_ACCOUNTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit payment account"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_PAYMENT_ACCOUNTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete payment account"
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
                        permission="RESTORE_PAYMENT_ACCOUNTS"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore payment account"
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
                label="payment accounts"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <PaymentAccountDetailDialog
        v-model:open="detailOpen"
        :account="viewingAccount"
    />

    <PaymentAccountFormDialog
        v-model:open="dialogOpen"
        :account="editingAccount"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this payment account?"
        description="It will be soft-deleted — you can restore it afterwards. Invoices that already used it keep their own copy of the details."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.label }}</p>
            <p class="text-muted-foreground">
                {{ paymentMethodLabel(pendingDelete.method) }} ·
                {{ currencyLabel(pendingDelete.currency) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${selectedActive.length} ${selectedActive.length === 1 ? 'account' : 'accounts'}?`"
        description="They will be soft-deleted — you can restore them afterwards."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="account in selectedActive"
                :key="account.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ account.label }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ currencyLabel(account.currency) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
