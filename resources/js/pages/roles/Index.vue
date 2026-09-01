<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    EyeIcon,
    PencilIcon,
    PlusIcon,
    RotateCcwIcon,
    ShieldCheckIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import RoleFormDialog from '@/modules/authorization/components/RoleFormDialog.vue';
import { useRoleMutations } from '@/modules/authorization/composables/useRoleMutations';
import {
    defaultRoleFilters,
    useRoles,
} from '@/modules/authorization/composables/useRoles';
import {
    formatDate,
    suspensionLabel,
    suspensionVariant,
} from '@/modules/authorization/helpers/authorizationPresentation';
import type {
    AuthorizationStatusFilter,
    PermissionName,
    Role,
} from '@/modules/authorization/types';
import { exportMethod, index, show } from '@/routes/roles';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

/**
 * `availablePermissions` and `protectedRoles` are shared by
 * `RoleController::index` and read straight off the page props: they are static
 * reference data for the whole screen, not server state the table paginates, so
 * mirroring them into the Pinia Colada cache would be duplication
 * (`FRONTEND/SKILL.md` §0 state-ownership rule). The rows themselves come from
 * `useRoles()` against the same route's JSON branch.
 */
const { availablePermissions = [], protectedRoles = [] } = defineProps<{
    availablePermissions?: readonly PermissionName[];
    protectedRoles?: readonly string[];
}>();

const { roles, meta: queryMeta, filters, isLoading } = useRoles();
const { deleteRole, restoreRole, bulkDeleteRoles, bulkRestoreRoles } =
    useRoleMutations();

const { can } = usePermissions();

// Restore search / status / date range / page from the URL on load, and mirror
// every later change back with `history.replaceState`. `per_page` is fixed, so
// it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultRoleFilters(),
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
    status: filters.value.status,
    date_from: filters.value.date_from ?? undefined,
    date_to: filters.value.date_to ?? undefined,
}));

const exportEndpoint = exportMethod.url();

/**
 * Two options, not the usual three: `RoleFilterData` validates
 * `in:active,suspended` and the repository has no "both" branch, so an "All"
 * entry would be a control that quietly returns the active list.
 */
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

/**
 * No `sortable` flags: both repositories hard-code `orderBy('name')`, so a sort
 * control would be a header the server never honours. Add them here the day the
 * filter DTO grows `sort_field` / `sort_order`.
 */
const columns: DataTableColumn<Role>[] = [
    {
        key: 'name',
        header: 'Role',
        value: (row) => row.name,
        align: 'left',
    },
    { key: 'permissions', header: 'Permissions', align: 'left' },
    {
        key: 'permissions_count',
        header: 'Granted',
        value: (row) => row.permissions_count,
        class: 'w-24',
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

const selection = ref<Role[]>([]);

function isProtected(role: Role): boolean {
    return protectedRoles.includes(role.name);
}

/**
 * System roles are excluded from every destructive path.
 *
 * `DeleteRoleHandler` throws `ProtectedRoleException` for them and the
 * controller answers `back()->with('error', …)`. That flash never reaches the
 * client — nothing shares it, and this module predates Inertia v3's
 * `Inertia::flash()` channel — so a submitted delete would look like a success
 * that silently did nothing. Filtering them out here means the UI never offers
 * the action; the server stays authoritative either way.
 */
const deletableSelection = computed(() =>
    selection.value.filter((role) => !role.deleted_at && !isProtected(role)),
);

const restorableSelection = computed(() =>
    selection.value.filter((role) => role.deleted_at !== null),
);

/** Selected system roles, so the confirmation can say why they were skipped. */
const skippedProtectedCount = computed(
    () =>
        selection.value.filter((role) => !role.deleted_at && isProtected(role))
            .length,
);

function rowClass(row: Role): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingRole = ref<Role | null>(null);

function openCreateDialog(): void {
    editingRole.value = null;
    dialogOpen.value = true;
}

function openEditDialog(role: Role): void {
    editingRole.value = role;
    dialogOpen.value = true;
}

function openDetail(role: Role): void {
    router.visit(show(role.uuid));
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<Role | null>(null);

function requestDelete(role: Role): void {
    pendingDelete.value = role;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useRoleMutations`) already toasted the failure, so re-throwing here would
 * only surface as an unhandled rejection with nothing left to do with it —
 * `ConfirmModal` / `DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteRole.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(role: Role): Promise<void> {
    await restoreRole.mutateAsync(role.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

/**
 * A selection of nothing but system roles has no deletable rows left after the
 * filter above, so opening the confirmation would ask the user to approve zero
 * suspensions. Say why instead.
 */
function requestBulkDelete(): void {
    if (deletableSelection.value.length === 0) {
        toast.info('System roles cannot be suspended.');

        return;
    }

    confirmBulkDeleteOpen.value = true;
}

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteRoles.mutateAsync(
            deletableSelection.value.map((role) => role.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreRoles.mutateAsync(
            restorableSelection.value.map((role) => role.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Roles" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Roles</h1>
            <p class="text-sm text-muted-foreground">
                Named bundles of permissions. Suspending a role revokes it
                everywhere immediately and is reversible; system roles are
                locked.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search role name…"
                    aria-label="Search roles"
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
                    :can-delete="can('BULK_DELETE_ROLES')"
                    :can-restore="can('BULK_RESTORE_ROLES')"
                    :busy="
                        bulkDeleteRoles.isLoading.value ||
                        bulkRestoreRoles.isLoading.value
                    "
                    @bulk-delete="requestBulkDelete"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_ROLES">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_ROLES">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New role
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
                :rows="roles"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Application roles"
                empty-title="No roles found"
                empty-description="Adjust the filters, or create a role to start grouping permissions."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:name`]="{ row }">
                    <span class="flex items-center gap-2">
                        <span class="font-medium">{{ row.name }}</span>
                        <ShieldCheckIcon
                            v-if="protectedRoles.includes(row.name)"
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            v-if="protectedRoles.includes(row.name)"
                            class="sr-only"
                        >
                            System role
                        </span>
                    </span>
                </template>

                <template #[`cell:permissions`]="{ row }">
                    <NameBadgeList
                        :names="row.permissions.map((item) => item.name)"
                        :max="3"
                        empty-label="No permissions"
                    />
                </template>

                <template #[`cell:status`]="{ row }">
                    <Badge :variant="suspensionVariant(row.deleted_at)">
                        {{ suspensionLabel(row.deleted_at) }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_ROLES">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="View role"
                            title="View"
                            @click="openDetail(row)"
                        >
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Button>
                    </PermissionGuard>

                    <template v-if="!row.deleted_at">
                        <PermissionGuard permission="UPDATE_ROLES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit role"
                                title="Edit"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_ROLES">
                            <Button
                                variant="ghost"
                                size="icon"
                                :disabled="protectedRoles.includes(row.name)"
                                :aria-label="
                                    protectedRoles.includes(row.name)
                                        ? `${row.name} is a system role and cannot be suspended`
                                        : 'Suspend role'
                                "
                                :title="
                                    protectedRoles.includes(row.name)
                                        ? 'System roles cannot be suspended'
                                        : 'Suspend'
                                "
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <PermissionGuard v-else permission="RESTORE_ROLES">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore role"
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
                label="roles"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <RoleFormDialog
        v-model:open="dialogOpen"
        :role="editingRole"
        :available-permissions="availablePermissions"
        :protected-roles="protectedRoles"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this role?"
        description="It is soft-deleted: everyone holding it loses its permissions immediately, and you can restore it afterwards."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.name }}</p>
            <p class="text-muted-foreground">
                {{ pendingDelete.permissions_count }}
                {{
                    pendingDelete.permissions_count === 1
                        ? 'permission'
                        : 'permissions'
                }}
                granted
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${deletableSelection.length} ${deletableSelection.length === 1 ? 'role' : 'roles'}?`"
        description="They are soft-deleted: everyone holding them loses those permissions immediately, and you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="role in deletableSelection"
                :key="role.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ role.name }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ role.permissions_count }}
                </span>
            </li>
        </ul>

        <p
            v-if="skippedProtectedCount > 0"
            class="text-sm text-muted-foreground"
        >
            {{ skippedProtectedCount }} selected
            {{ skippedProtectedCount === 1 ? 'role is' : 'roles are' }}
            a system role and will be left untouched.
        </p>
    </ConfirmModal>
</template>
