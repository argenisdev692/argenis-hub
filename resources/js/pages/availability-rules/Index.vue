<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarCogIcon,
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
import type { DataTableColumn, PaginationMeta } from '@/common/table';
import {
    ConfirmModal,
    DataTable,
    DataTableBulkActions,
    DataTableExportMenu,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import AvailabilityOpenBadge from '@/modules/availability/components/AvailabilityOpenBadge.vue';
import AvailabilityRuleFormDialog from '@/modules/availability/components/AvailabilityRuleFormDialog.vue';
import AvailabilityStatusBadge from '@/modules/availability/components/AvailabilityStatusBadge.vue';
import { useAvailabilityRuleMutations } from '@/modules/availability/composables/useAvailabilityRuleMutations';
import {
    buildAvailabilityRuleQueryParams,
    defaultAvailabilityRuleFilters,
    useAvailabilityRules,
} from '@/modules/availability/composables/useAvailabilityRules';
import {
    availabilityRuleLabel,
    DAY_OF_WEEK_VALUES,
    dayOfWeekLabel,
    formatDate,
    formatTimeRange,
    isDayOfWeek,
} from '@/modules/availability/helpers/availabilityPresentation';
import type {
    AvailabilityRule,
    AvailabilityStatusFilter,
} from '@/modules/availability/types';
import { index as exceptionsIndex } from '@/routes/availability-exceptions';
import { exportMethod, index, show } from '@/routes/availability-rules';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Availability rules', href: index() }],
    },
});

const {
    availabilityRules,
    meta: queryMeta,
    filters,
    isLoading,
} = useAvailabilityRules();

const { deleteRule, restoreRule, bulkDeleteRules, bulkRestoreRules } =
    useAvailabilityRuleMutations();

const { can } = usePermissions();

// Restore weekday / availability / status / page from the URL on load, and
// mirror every later change back with `history.replaceState`. `per_page` is
// fixed, so it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultAvailabilityRuleFilters(),
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
 * There is no search box on this page, and that is deliberate.
 *
 * `availability_rules` carries a weekday, two times and a boolean — no free-text
 * column exists for a term to match against, so a search input here would be a
 * control that does nothing. The two selects below are the filters this entity
 * actually has. (The date-exceptions list does have a `reason`, and does search
 * on it.)
 */
const dayOptions: FilterSelectOption[] = [
    { value: 'all', label: 'Every day' },
    ...DAY_OF_WEEK_VALUES.map((day) => ({
        value: day,
        label: dayOfWeekLabel(day),
    })),
];

function onDayChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.day_of_week = isDayOfWeek(value) ? value : 'all';
    filters.value.page = 1;
}

const availabilityOptions: FilterSelectOption[] = [
    { value: 'all', label: 'Any slot' },
    { value: 'available', label: 'Available' },
    { value: 'unavailable', label: 'Unavailable' },
];

function onAvailabilityChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.availability =
        value === 'available' || value === 'unavailable' ? value : 'all';
    filters.value.page = 1;
}

/**
 * Two options, not the usual three.
 *
 * `EloquentAvailabilityRuleRepository::paginate()` only branches on `suspended`
 * (→ `onlyTrashed()`); every other value leaves the `SoftDeletes` global scope
 * in place. An "All" option would therefore return exactly what "Active"
 * returns, and a filter that quietly does nothing is worse than one that is not
 * offered.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        value === 'suspended' ? 'suspended' : 'active'
    ) satisfies AvailabilityStatusFilter;
    filters.value.page = 1;
    // The two views never share a row, so a selection made in one is meaningless
    // in the other — and a stale uuid in it would arm a bulk action against a
    // row that is no longer on screen.
    selection.value = [];
}

/**
 * The active filter set, as the export endpoint's query string wants it. Built
 * by the same helper the list query uses, so the download can never cover a
 * different set than the one on screen.
 */
const exportParams = computed(() =>
    buildAvailabilityRuleQueryParams(filters.value),
);

const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<AvailabilityRule>[] = [
    {
        key: 'day_of_week',
        header: 'Weekday',
        value: (row) => dayOfWeekLabel(row.day_of_week),
        align: 'left',
        class: 'min-w-32',
    },
    {
        key: 'hours',
        header: 'Hours',
        value: (row) => formatTimeRange(row.start_time, row.end_time),
        class: 'min-w-40',
    },
    { key: 'is_available', header: 'Slot' },
    { key: 'status', header: 'Status' },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => formatDate(row.created_at),
        hideOnMobile: true,
    },
];

const page = computed<number>({
    get: () => filters.value.page,
    set: (value) => {
        filters.value.page = value;
    },
});

const selection = ref<AvailabilityRule[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((row) => row.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((row) => row.deleted_at !== null),
);

function rowClass(row: AvailabilityRule): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const dialogOpen = ref(false);
const editingRule = ref<AvailabilityRule | null>(null);

function openCreateDialog(): void {
    editingRule.value = null;
    dialogOpen.value = true;
}

function openEditDialog(rule: AvailabilityRule): void {
    editingRule.value = rule;
    dialogOpen.value = true;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<AvailabilityRule | null>(null);

function requestDelete(rule: AvailabilityRule): void {
    pendingDelete.value = rule;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useAvailabilityRuleMutations`) already toasted the failure, so re-throwing
 * here would only surface as an unhandled rejection with nothing left to do
 * with it — `ConfirmModal`/`DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteRule.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(rule: AvailabilityRule): Promise<void> {
    await restoreRule.mutateAsync(rule.uuid).catch(() => undefined);
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteRules.mutateAsync(
            selectedActive.value.map((row) => row.uuid),
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
        await bulkRestoreRules.mutateAsync(
            selectedDeleted.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkRestoreOpen.value = false;
}
</script>

<template>
    <Head title="Availability rules" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Availability rules
                </h1>
                <p class="text-sm text-muted-foreground">
                    The recurring weekly template. National holidays and date
                    exceptions override it —
                    {{ meta.total }}
                    {{ meta.total === 1 ? 'record' : 'records' }} found.
                </p>
            </div>

            <PermissionGuard permission="VIEW_ANY_AVAILABILITY_EXCEPTIONS">
                <Button as-child variant="outline">
                    <Link :href="exceptionsIndex()">
                        <CalendarCogIcon class="size-4" aria-hidden="true" />
                        Date exceptions
                    </Link>
                </Button>
            </PermissionGuard>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #filters>
                <FilterSelect
                    class="w-40"
                    :options="dayOptions"
                    :clearable="false"
                    :model-value="filters.day_of_week"
                    @update:model-value="onDayChange"
                />

                <FilterSelect
                    class="w-40"
                    :options="availabilityOptions"
                    :clearable="false"
                    :model-value="filters.availability"
                    @update:model-value="onAvailabilityChange"
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
                    :can-delete="can('BULK_DELETE_AVAILABILITY_RULES')"
                    :can-restore="can('BULK_RESTORE_AVAILABILITY_RULES')"
                    :busy="
                        bulkDeleteRules.isLoading.value ||
                        bulkRestoreRules.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="confirmBulkRestoreOpen = true"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_AVAILABILITY_RULES">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['xlsx', 'csv', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_AVAILABILITY_RULES">
                    <Button @click="openCreateDialog">
                        <PlusIcon class="size-4" aria-hidden="true" />
                        New rule
                    </Button>
                </PermissionGuard>
            </template>
        </DataTableToolbar>

        <div class="flex flex-col">
            <DataTable
                v-model:selection="selection"
                :rows="availabilityRules"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="Weekly availability template"
                empty-title="No availability rules yet"
                empty-description="Add the first weekly slot to start accepting appointments."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:is_available`]="{ row }">
                    <AvailabilityOpenBadge
                        :is-available="row.is_available"
                        wording="available"
                    />
                </template>

                <template #[`cell:status`]="{ row }">
                    <AvailabilityStatusBadge :deleted-at="row.deleted_at" />
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_AVAILABILITY_RULES">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="View availability rule"
                        >
                            <Link :href="show(row.uuid)">
                                <EyeIcon class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </PermissionGuard>

                    <template v-if="row.deleted_at === null">
                        <PermissionGuard permission="UPDATE_AVAILABILITY_RULES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit availability rule"
                                @click="openEditDialog(row)"
                            >
                                <PencilIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_AVAILABILITY_RULES">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend availability rule"
                                @click="requestDelete(row)"
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                            </Button>
                        </PermissionGuard>
                    </template>

                    <!-- Never Edit on a suspended row: restore it first. -->
                    <PermissionGuard
                        v-else
                        permission="RESTORE_AVAILABILITY_RULES"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore availability rule"
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
                label="availability rules"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <AvailabilityRuleFormDialog v-model:open="dialogOpen" :rule="editingRule" />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this availability rule?"
        description="It will be soft-deleted and stop being offered to bookings — you can restore it afterwards. Appointments already booked are not touched."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">
                {{ availabilityRuleLabel(pendingDelete) }}
            </p>
            <p class="text-muted-foreground">
                {{
                    pendingDelete.is_available
                        ? 'Available slot'
                        : 'Unavailable slot'
                }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'rule' : 'rules'}?`"
        description="They will be soft-deleted and stop being offered to bookings — you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="rule in selectedActive"
                :key="rule.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ dayOfWeekLabel(rule.day_of_week) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ formatTimeRange(rule.start_time, rule.end_time) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${selectedDeleted.length} ${selectedDeleted.length === 1 ? 'rule' : 'rules'}?`"
        description="They return to the weekly template. A slot that now overlaps another available one is rejected."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="rule in selectedDeleted"
                :key="rule.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">
                    {{ dayOfWeekLabel(rule.day_of_week) }}
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ formatTimeRange(rule.start_time, rule.end_time) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
