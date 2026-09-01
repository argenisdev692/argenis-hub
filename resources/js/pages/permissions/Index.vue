<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import NameBadgeList from '@/modules/authorization/components/NameBadgeList.vue';
import PermissionFormDialog from '@/modules/authorization/components/PermissionFormDialog.vue';
import {
    defaultPermissionFilters,
    usePermissionCatalog,
} from '@/modules/authorization/composables/usePermissionCatalog';
import { usePermissionMutations } from '@/modules/authorization/composables/usePermissionMutations';
import {
    formatDate,
    suspensionLabel,
    suspensionVariant,
} from '@/modules/authorization/helpers/authorizationPresentation';
import type {
    AuthorizationStatusFilter,
    Permission,
} from '@/modules/authorization/types';
import { exportMethod, index, show } from '@/routes/permissions';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Permissions', href: index() }],
    },
});

const {
    permissions: rows,
    meta: queryMeta,
    filters,
    isLoading,
} = usePermissionCatalog();

const {
    deletePermission,
    restorePermission,
    bulkDeletePermissions,
    bulkRestorePermissions,
} = usePermissionMutations();

/**
 * `can` answers "what may the *viewer* do", which is a different question from
 * the records this screen manages — hence the aliased import beside
 * `usePermissionCatalog` above.
 */
const { can } = usePermissions();

useUrlSyncedFilters(filters, {
    defaults: defaultPermissionFilters(),
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

const exportParams = computed(() => ({
    search: filters.value.search || undefined,
    status: filters.value.status,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
}));

const exportEndpoint = exportMethod.url();

/** Two options only — see the note in `roles/Index.vue`. */
const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'active'
    ) as AuthorizationStatusFilter;
    filters.value.page = 1;
    selection.value = [];
}

const columns: DataTableColumn<Permission>[] = [
    {
        key: 'name',
        header: 'Permission',
        value: (row) => row.name,
        align: 'left',
        class: 'font-mono',
    },
    { key: 'roles', header: 'Held by', align: 'left' },
    {
        key: 'roles_count',
        header: 'Roles',
        value: (row) => row.roles_count,
        class: 'w-20',
    },
    {
        key: 'guard_name',
        header: 'Guard',
        value: (row) => row.guard_name,
        hideOnMobile: true,
        class: 'w-24',
    },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => formatDate(row.created_at),
        hideOnMobile: true,
    },
    { key: 'status', header: 'Status', class: 'w-28' },
];

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

const selection = ref<Permission[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((permission) => permission.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((permission) => permission.deleted_at !== null),
);

/**
 * Suspending a permission strips it from every role that holds it, so the
 * confirmation leads with the blast radius rather than the row count.
 */
const affectedRoleCount = computed(() =>
    selectedActive.value.reduce(
        (total, permission) => total + permission.roles_count,
        0,
    ),
);

function rowClass(row: Permission): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingPermission = ref<Permission | null>(null);

function openCreateDialog(): void {
    editingPermission.value = null;
    dialogOpen.value = true;
}

function openEditDialog(permission: Permission): void {
    editingPermission.value = permission;
    dialogOpen.value = true;
}

function openDetail(permission: Permission): void {
    router.visit(show(permission.uuid));
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Permission | null>(null);

function requestDelete(permission: Permission): void {
    pendingDelete.value = permission;
    confirmDeleteOpen.value = true;
}

/** Failures are already toasted by the mutations — see `roles/Index.vue`. */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deletePermission.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(permission: Permission): Promise<void> {
    await restorePermission.mutateAsync(permission.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeletePermissions.mutateAsync(
            selectedActive.value.map((permission) => permission.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestorePermissions.mutateAsync(
            selectedDeleted.value.map((permission) => permission.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Permissions" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Permissions</h1>
            <p class="text-sm text-muted-foreground">
                The catalogue roles are built from, named
                <span class="font-mono">{ACTION}_{MODULE}</span>. Suspending one
                revokes it from every role that holds it, reversibly.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search permission name…"
                    aria-label="Search permissions"
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
                    :can-delete="can('BULK_DELETE_PERMISSIONS')"
                    :can-restore="can('BULK_RESTORE_PERMISSIONS')"
                    :busy="
                        bulkDeletePermissions.isLoading.value ||
                        bulkRestorePermissions.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_PERMISSIONS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_PERMISSIONS">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New permission
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
                :rows="rows"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Permission catalogue"
                empty-title="No permissions found"
                empty-description="Adjust the filters, or add the first permission to this catalogue."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:roles`]="{ row }">
                    <NameBadgeList
                        :names="row.roles.map((item) => item.name)"
                        :max="3"
                        empty-label="Unassigned"
                    />
                </template>

                <template #[`cell:status`]="{ row }">
                    <Badge :variant="suspensionVariant(row.deleted_at)">
                        {{ suspensionLabel(row.deleted_at) }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_PERMISSIONS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View permission"
                            title="View"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_PERMISSIONS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit permission"
                                title="Edit"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_PERMISSIONS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend permission"
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

                    <PermissionGuard v-else permission="RESTORE_PERMISSIONS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore permission"
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
                label="permissions"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <PermissionFormDialog
        v-model:open="dialogOpen"
        :permission="editingPermission"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this permission?"
        description="It is soft-deleted and stripped from every role that holds it. You can restore it afterwards."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-mono font-medium">{{ pendingDelete.name }}</p>
            <p class="text-muted-foreground">
                Held by {{ pendingDelete.roles_count }}
                {{ pendingDelete.roles_count === 1 ? 'role' : 'roles' }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'permission' : 'permissions'}?`"
        :description="`They are soft-deleted and stripped from every role that holds them — ${affectedRoleCount} role ${affectedRoleCount === 1 ? 'grant' : 'grants'} in total. You can restore them afterwards.`"
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="permission in selectedActive"
                :key="permission.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-mono font-medium">
                    {{ permission.name }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ permission.roles_count }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
