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
import { useClientOptions } from '@/modules/clients/composables/useClientOptions';
import ProductDetailDialog from '@/modules/products/components/ProductDetailDialog.vue';
import ProductFormDialog from '@/modules/products/components/ProductFormDialog.vue';
import { useProductMutations } from '@/modules/products/composables/useProductMutations';
import {
    defaultProductFilters,
    useProducts,
} from '@/modules/products/composables/useProducts';
import {
    formatDate,
    formatUnitPrice,
    productStatusLabel,
    productStatusVariant,
    productTypeLabel,
} from '@/modules/products/helpers/productPresentation';
import {
    PRODUCT_STATUS_VALUES,
    PRODUCT_TYPE_VALUES,
} from '@/modules/products/schemas/productFormSchema';
import type {
    Product,
    ProductRowStatusFilter,
    ProductStatus,
    ProductType,
} from '@/modules/products/types';
import { index } from '@/routes/products';
import { exportMethod } from '@/routes/products/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});

const { products, meta: queryMeta, filters, isLoading } = useProducts();
const {
    deleteProduct,
    restoreProduct,
    bulkDeleteProducts,
    bulkRestoreProducts,
} = useProductMutations();

const { can } = usePermissions();
const { clientOptions } = useClientOptions();

// Restore search / status / type / date range / page / sort from the URL on
// load, and mirror every later change back with `history.replaceState`.
// `per_page` is fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultProductFilters(),
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
    type: filters.value.type ?? undefined,
    product_status: filters.value.product_status ?? undefined,
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

const typeOptions: FilterSelectOption[] = PRODUCT_TYPE_VALUES.map((type) => ({
    value: type,
    label: productTypeLabel(type),
}));

const catalogStatusOptions: FilterSelectOption[] = PRODUCT_STATUS_VALUES.map(
    (status) => ({ value: status, label: productStatusLabel(status) }),
);

function onRowStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as ProductRowStatusFilter;
    filters.value.page = 1;
}

function onTypeChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.type = typeof value === 'string' ? (value as ProductType) : null;
    filters.value.page = 1;
}

function onCatalogStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.product_status =
        typeof value === 'string' ? (value as ProductStatus) : null;
    filters.value.page = 1;
}

const columns: DataTableColumn<Product>[] = [
    {
        key: 'title',
        header: 'Title',
        value: (row) => row.title,
        sortable: true,
        align: 'left',
    },
    { key: 'type', header: 'Type' },
    {
        key: 'client_name',
        header: 'Client',
        value: (row) => row.client_name,
        align: 'left',
        hideOnMobile: true,
    },
    { key: 'price', header: 'Price', sortable: true },
    {
        key: 'total_hours',
        header: 'Hours',
        value: (row) => (row.total_hours === null ? null : String(row.total_hours)),
        hideOnMobile: true,
    },
    { key: 'status', header: 'Catalog', sortable: true },
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

const selection = ref<Product[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((product) => product.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((product) => product.deleted_at !== null),
);

function rowClass(row: Product): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const detailOpen = ref(false);
const viewingProduct = ref<Product | null>(null);

function openDetail(product: Product): void {
    viewingProduct.value = product;
    detailOpen.value = true;
}

const dialogOpen = ref(false);
const editingProduct = ref<Product | null>(null);

function openCreateDialog(): void {
    editingProduct.value = null;
    dialogOpen.value = true;
}

function openEditDialog(product: Product): void {
    editingProduct.value = product;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Product | null>(null);

function requestDelete(product: Product): void {
    pendingDelete.value = product;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useProductMutations`) already toasts the failure, so re-throwing here would
 * only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal` / `DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteProduct.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(product: Product): Promise<void> {
    await restoreProduct.mutateAsync(product.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteProducts.mutateAsync(
            selectedActive.value.map((product) => product.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreProducts.mutateAsync(
            selectedDeleted.value.map((product) => product.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Products" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Products</h1>
            <p class="text-sm text-muted-foreground">
                The billable catalog — live training courses and recorded video
                courses. Published entries appear in the invoice line-item
                picker with their price and unit pre-filled.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search title or slug…"
                    aria-label="Search products"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
                />

                <FilterSelect
                    class="w-40"
                    placeholder="Any type"
                    :options="typeOptions"
                    :model-value="filters.type"
                    @update:model-value="onTypeChange"
                />

                <FilterSelect
                    class="w-36"
                    placeholder="Any catalog"
                    :options="catalogStatusOptions"
                    :model-value="filters.product_status"
                    @update:model-value="onCatalogStatusChange"
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
                    :can-delete="can('BULK_DELETE_PRODUCTS')"
                    :can-restore="can('BULK_RESTORE_PRODUCTS')"
                    :busy="
                        bulkDeleteProducts.isLoading.value ||
                        bulkRestoreProducts.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_PRODUCTS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_PRODUCTS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New product
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
                :rows="products"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Billable catalog"
                empty-title="No products yet"
                empty-description="Create a course or a video course to bill it from an invoice."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:type`]="{ row }">
                    <Badge variant="outline">
                        {{ productTypeLabel(row.type) }}
                    </Badge>
                </template>

                <template #[`cell:price`]="{ row }">
                    <span class="tabular-nums">
                        {{
                            formatUnitPrice(
                                row.price,
                                row.currency,
                                row.default_unit,
                            )
                        }}
                    </span>
                </template>

                <template #[`cell:status`]="{ row }">
                    <Badge :variant="productStatusVariant(row.status)">
                        {{ productStatusLabel(row.status) }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_PRODUCTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View product"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_PRODUCTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit product"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_PRODUCTS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete product"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_PRODUCTS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore product"
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
                label="products"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ProductDetailDialog v-model:open="detailOpen" :product="viewingProduct" />

    <ProductFormDialog
        v-model:open="dialogOpen"
        :product="editingProduct"
        :client-options="clientOptions"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this product?"
        description="It will be soft-deleted — you can restore it afterwards. Invoice lines already billed against it keep their text."
        confirm-label="Delete"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.title }}</p>
            <p class="text-muted-foreground">
                {{ productTypeLabel(pendingDelete.type) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Delete ${selectedActive.length} ${selectedActive.length === 1 ? 'product' : 'products'}?`"
        description="They will be soft-deleted — you can restore them afterwards."
        confirm-label="Delete"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="product in selectedActive"
                :key="product.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ product.title }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ productTypeLabel(product.type) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
