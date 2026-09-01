<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    FileTextIcon,
    PencilIcon,
    RotateCcwIcon,
    SendIcon,
    SparklesIcon,
    Trash2Icon,
    TriangleAlertIcon,
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
import CampaignStatusBadge from '@/modules/campaigns/components/CampaignStatusBadge.vue';
import { useCampaignMutations } from '@/modules/campaigns/composables/useCampaignMutations';
import {
    buildCampaignQueryParams,
    defaultCampaignFilters,
    useCampaigns,
} from '@/modules/campaigns/composables/useCampaigns';
import {
    campaignAdFormatLabel,
    campaignBusinessGoalLabel,
    campaignFunnelStageLabel,
    campaignPlatformLabel,
    formatDate,
    scoreTextClass,
    scoreTone,
    successProbabilityLabel,
} from '@/modules/campaigns/helpers/campaignPresentation';
import type {
    CampaignListItem,
    CampaignStatusFilter,
} from '@/modules/campaigns/types';
import { create, edit, exportMethod, index, report } from '@/routes/campaigns';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: index() }],
    },
});

const { campaigns, meta: queryMeta, filters, isLoading } = useCampaigns();

const {
    deleteCampaign,
    restoreCampaign,
    publishCampaign,
    bulkDeleteCampaigns,
    bulkRestoreCampaigns,
} = useCampaignMutations();

const { can } = usePermissions();

// Restore search / status / date range / page from the URL on load, and mirror
// every later change back with `history.replaceState`. `per_page` is fixed, so
// it stays out of the query string.
useUrlSyncedFilters(filters, {
    defaults: defaultCampaignFilters(),
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

/**
 * One axis for the lifecycle and the soft-delete state, because that is how the
 * backend reads it — see `CampaignStatusFilter`.
 */
const statusOptions: FilterSelectOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'generating', label: 'Generating' },
    { value: 'ready', label: 'Ready' },
    { value: 'needs_review', label: 'Needs review' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'published', label: 'Published' },
    { value: 'suspended', label: 'Suspended' },
];

function onStatusChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    filters.value.status = (
        typeof value === 'string' ? value : 'all'
    ) as CampaignStatusFilter;
    filters.value.page = 1;
}

/**
 * The active filter set, as the export endpoint's query string wants it. Built
 * by the same helper the list query uses, so the download can never cover a
 * different set than the one on screen.
 */
const exportParams = computed(() => buildCampaignQueryParams(filters.value));

const exportEndpoint = exportMethod.url();

const columns: DataTableColumn<CampaignListItem>[] = [
    {
        key: 'topic',
        header: 'Topic',
        value: (row) => row.topic,
        align: 'left',
        class: 'min-w-64',
    },
    { key: 'status', header: 'Status' },
    {
        key: 'platform',
        header: 'Placement',
        hideOnMobile: true,
    },
    {
        key: 'business_goal',
        header: 'Goal',
        value: (row) => campaignBusinessGoalLabel(row.business_goal),
        hideOnMobile: true,
    },
    {
        key: 'funnel_stage',
        header: 'Funnel',
        value: (row) => campaignFunnelStageLabel(row.funnel_stage),
        hideOnMobile: true,
    },
    { key: 'overall_score_avg', header: 'Quality' },
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

const selection = ref<CampaignListItem[]>([]);

const selectedActive = computed(() =>
    selection.value.filter((row) => row.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((row) => row.deleted_at !== null),
);

function rowClass(row: CampaignListItem): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

/**
 * A campaign still being generated has no copy to review and no scores to judge
 * it by, so the edit affordance stays out until the job settles.
 */
function isEditable(row: CampaignListItem): boolean {
    return row.deleted_at === null && row.status !== 'generating';
}

/** Publishing something already published, or still generating, is a no-op. */
function isPublishable(row: CampaignListItem): boolean {
    return (
        row.deleted_at === null &&
        row.status !== 'generating' &&
        row.status !== 'published'
    );
}

const recordCounter = computed(() => {
    const total = meta.value.total;

    return `${total} ${total === 1 ? 'record' : 'records'} found`;
});

/**
 * The per-campaign PDF scorecard.
 *
 * A plain `<a>` rather than an Inertia `Link`: the route streams a
 * `Content-Disposition` attachment, not an Inertia response, so a client-side
 * visit would leave the router waiting for a page that never arrives.
 */
function reportUrl(uuid: string): string {
    return report.url(uuid);
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<CampaignListItem | null>(null);

function requestDelete(row: CampaignListItem): void {
    pendingDelete.value = row;
    confirmDeleteOpen.value = true;
}

/**
 * Every handler below catches and discards: the mutation's own `onError` (in
 * `useCampaignMutations`) already toasted the failure, so re-throwing here
 * would only surface as an unhandled rejection with nothing left to do with
 * it — `ConfirmModal`/`DataTable` invoke these as fire-and-forget.
 */
async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteCampaign.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

async function onRestoreRow(row: CampaignListItem): Promise<void> {
    await restoreCampaign.mutateAsync(row.uuid).catch(() => undefined);
}

const confirmPublishOpen = ref(false);
const pendingPublish = ref<CampaignListItem | null>(null);

function requestPublish(row: CampaignListItem): void {
    pendingPublish.value = row;
    confirmPublishOpen.value = true;
}

/**
 * Publishing is confirmed rather than immediate: it is the one row action here
 * that commits a campaign to the ad calendar, and `PUBLISH_CAMPAIGNS` is a
 * separate permission from `UPDATE_CAMPAIGNS` for exactly that reason.
 */
async function confirmPublish(): Promise<void> {
    if (!pendingPublish.value) {
        return;
    }

    try {
        await publishCampaign.mutateAsync(pendingPublish.value.uuid);
    } catch {
        return;
    }

    confirmPublishOpen.value = false;
    pendingPublish.value = null;
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeleteCampaigns.mutateAsync(
            selectedActive.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkDeleteOpen.value = false;
}

async function onBulkRestore(): Promise<void> {
    try {
        await bulkRestoreCampaigns.mutateAsync(
            selectedDeleted.value.map((row) => row.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
}
</script>

<template>
    <Head title="Campaigns" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Campaigns</h1>
            <p class="text-sm text-muted-foreground">
                AI-generated Meta lead-gen campaigns — review the copy and the
                five quality scores, then schedule or publish.
            </p>
        </header>

        <DataTableToolbar
            :selected-count="selection.length"
            selection-label="selected"
        >
            <template #search>
                <DataTableSearch
                    v-model="searchTerm"
                    placeholder="Search topic or headline…"
                    aria-label="Search campaigns"
                />
            </template>

            <template #filters>
                <DataTableDateRangeFilter
                    v-model="dateRange"
                    placeholder="Created any time"
                />

                <FilterSelect
                    class="w-44"
                    :options="statusOptions"
                    :clearable="false"
                    :model-value="filters.status"
                    @update:model-value="onStatusChange"
                />
            </template>

            <template #bulk>
                <DataTableBulkActions
                    :selection="selection"
                    :can-delete="can('BULK_DELETE_CAMPAIGNS')"
                    :can-restore="can('BULK_RESTORE_CAMPAIGNS')"
                    :busy="
                        bulkDeleteCampaigns.isLoading.value ||
                        bulkRestoreCampaigns.isLoading.value
                    "
                    @bulk-delete="confirmBulkDeleteOpen = true"
                    @bulk-restore="onBulkRestore"
                />
            </template>

            <template #actions>
                <PermissionGuard permission="EXPORT_CAMPAIGNS">
                    <DataTableExportMenu
                        :endpoint="exportEndpoint"
                        :params="exportParams"
                        :formats="['csv', 'xlsx', 'pdf']"
                    />
                </PermissionGuard>

                <PermissionGuard permission="CREATE_CAMPAIGNS">
                    <Button as-child>
                        <Link :href="create()">
                            <SparklesIcon class="size-4" aria-hidden="true" />
                            Generate campaign
                        </Link>
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
                :rows="campaigns"
                :columns="columns"
                :loading="isLoading"
                :row-class="rowClass"
                selectable
                caption="AI-generated Meta lead-gen campaigns"
                empty-title="No campaigns yet"
                empty-description="Generate the first one to see it here."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:topic`]="{ row }">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-medium">{{
                            row.topic
                        }}</span>
                        <TriangleAlertIcon
                            v-if="row.quality_warning"
                            class="size-4 shrink-0 text-warning"
                            aria-label="Finished below the quality threshold"
                        />
                    </div>
                </template>

                <template #[`cell:status`]="{ row }">
                    <CampaignStatusBadge
                        :status="row.status"
                        :deleted-at="row.deleted_at"
                    />
                </template>

                <template #[`cell:platform`]="{ row }">
                    <div class="flex flex-col items-center gap-1">
                        <span>{{ campaignPlatformLabel(row.platform) }}</span>
                        <Badge variant="outline">
                            {{ campaignAdFormatLabel(row.ad_format) }}
                        </Badge>
                    </div>
                </template>

                <template #[`cell:overall_score_avg`]="{ row }">
                    <div
                        v-if="row.overall_score_avg !== null"
                        class="flex flex-col items-center gap-0.5"
                    >
                        <span
                            class="font-semibold tabular-nums"
                            :class="
                                scoreTextClass(scoreTone(row.overall_score_avg))
                            "
                        >
                            {{ row.overall_score_avg }}
                        </span>
                        <span
                            v-if="row.success_probability_label"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                successProbabilityLabel(
                                    row.success_probability_label,
                                )
                            }}
                        </span>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                </template>

                <template #actions="{ row }">
                    <PermissionGuard permission="VIEW_CAMPAIGNS">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            aria-label="Download campaign report"
                        >
                            <a :href="reportUrl(row.uuid)">
                                <FileTextIcon
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </Button>
                    </PermissionGuard>

                    <template v-if="row.deleted_at === null">
                        <!-- Never Edit while a campaign is still generating. -->
                        <PermissionGuard
                            v-if="isEditable(row)"
                            permission="VIEW_CAMPAIGNS"
                        >
                            <Button
                                as-child
                                variant="ghost"
                                size="icon"
                                aria-label="Review campaign"
                            >
                                <Link :href="edit(row.uuid)">
                                    <PencilIcon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard
                            v-if="isPublishable(row)"
                            permission="PUBLISH_CAMPAIGNS"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Publish campaign"
                                @click="requestPublish(row)"
                            >
                                <SendIcon class="size-4" aria-hidden="true" />
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="DELETE_CAMPAIGNS">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Suspend campaign"
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
                    <PermissionGuard v-else permission="RESTORE_CAMPAIGNS">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Restore campaign"
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
                label="campaigns"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this campaign?"
        description="It will be soft-deleted and drop out of the scheduler — you can restore it afterwards."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    >
        <div
            v-if="pendingDelete"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingDelete.topic }}</p>
            <p class="text-muted-foreground">
                {{ campaignBusinessGoalLabel(pendingDelete.business_goal) }} ·
                {{ campaignPlatformLabel(pendingDelete.platform) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmPublishOpen"
        title="Publish this campaign?"
        description="It is marked live immediately and stops being picked up by the scheduler."
        confirm-label="Publish"
        @confirm="confirmPublish"
    >
        <div
            v-if="pendingPublish"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ pendingPublish.topic }}</p>
            <p class="text-muted-foreground">
                Quality {{ pendingPublish.overall_score_avg ?? '—' }} ·
                {{ campaignFunnelStageLabel(pendingPublish.funnel_stage) }}
            </p>
        </div>
    </ConfirmModal>

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} ${selectedActive.length === 1 ? 'campaign' : 'campaigns'}?`"
        description="They will be soft-deleted and drop out of the scheduler — you can restore them afterwards."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    >
        <ul
            class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <li
                v-for="row in selectedActive"
                :key="row.uuid"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="truncate font-medium">{{ row.topic }}</span>
                <span class="shrink-0 text-muted-foreground">
                    {{ campaignFunnelStageLabel(row.funnel_stage) }}
                </span>
            </li>
        </ul>
    </ConfirmModal>
</template>
