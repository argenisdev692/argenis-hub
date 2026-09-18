<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    EyeIcon,
    LinkIcon,
    PlusIcon,
    RotateCcwIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, ref, useId } from 'vue';
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
    DataTableRowAction,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useUrlSyncedFilters } from '@/composables/useUrlSyncedFilters';
import FitScoreBadge from '@/modules/cv-studio/components/FitScoreBadge.vue';
import PostingStatusBadge from '@/modules/cv-studio/components/PostingStatusBadge.vue';
import { usePostingMutations } from '@/modules/cv-studio/composables/usePostingMutations';
import {
    defaultPostingFilters,
    usePostings,
} from '@/modules/cv-studio/composables/usePostings';
import {
    usePasteJobText,
    useReferences,
} from '@/modules/cv-studio/composables/useReferences';
import { buildPostingQueryParams } from '@/modules/cv-studio/helpers/buildPostingQueryParams';
import {
    formatDate,
    remoteScopeLabel,
} from '@/modules/cv-studio/helpers/studioPresentation';
import type {
    StudioPosting,
    StudioReference,
    StudioStatusFilter,
} from '@/modules/cv-studio/types';
import {
    exportMethod,
    index,
    show,
} from '@/routes/cv-studio/postings';
import { store as storeRun } from '@/routes/cv-studio/runs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'CV Studio', href: index() }],
    },
});

const { postings, meta: queryMeta, filters, isLoading } = usePostings();
const {
    deletePosting,
    restorePosting,
    bulkDeletePostings,
    bulkRestorePostings,
} = usePostingMutations();
const {
    references,
    total: referencesTotal,
    pageCount: referencesPageCount,
    page: referencesPage,
    isLoading: referencesLoading,
} = useReferences();
const { pasteText } = usePasteJobText();

const { can } = usePermissions();
const selection = ref<StudioPosting[]>([]);

useUrlSyncedFilters(filters, {
    defaults: defaultPostingFilters(),
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
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) => {
        filters.value.date_from = value.from;
        filters.value.date_to = value.to;
        filters.value.page = 1;
    },
});

const exportParams = computed(() => buildPostingQueryParams(filters.value));
const exportEndpoint = exportMethod.url();

const statusOptions: FilterSelectOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'all', label: 'All' },
];

const scopeOptions: FilterSelectOption[] = [
    { value: 'remote_global', label: 'Remote · Global' },
    { value: 'remote_eu', label: 'Remote · EU' },
    { value: 'remote_pt_es', label: 'Remote · PT/ES' },
    { value: 'remote_unclear', label: 'Remote · Unclear' },
    { value: 'hybrid_local', label: 'Hybrid / Local' },
];

type FilterSelectModel =
    FilterSelectOption['value'] | FilterSelectOption['value'][] | null;

function isStatusFilter(value: unknown): value is StudioStatusFilter {
    return statusOptions.some((option) => option.value === value);
}

function applyFacet(apply: () => void): void {
    apply();
    filters.value.page = 1;
    selection.value = [];
}

function onStatusChange(value: FilterSelectModel): void {
    applyFacet(() => {
        filters.value.status = isStatusFilter(value) ? value : 'active';
    });
}

function onScopeChange(value: FilterSelectModel): void {
    applyFacet(() => {
        filters.value.remote_scope =
            typeof value === 'string' ? value : '';
    });
}

const columns: DataTableColumn<StudioPosting>[] = [
    { key: 'title', header: 'Title', align: 'left' },
    { key: 'employer', header: 'Employer', hideOnMobile: true },
    { key: 'scope', header: 'Scope', hideOnMobile: true },
    { key: 'fit', header: 'Fit' },
    { key: 'status', header: 'Status' },
    {
        key: 'created_at',
        header: 'Found',
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

const selectedActive = computed(() =>
    selection.value.filter((posting) => posting.deleted_at === null),
);
const selectedDeleted = computed(() =>
    selection.value.filter((posting) => posting.deleted_at !== null),
);

function rowClass(row: StudioPosting): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

const tab = ref<'postings' | 'manual'>('postings');
const pasteOpen = ref(false);
const pasteTarget = ref<StudioReference | null>(null);
const pasteBody = ref('');

const PASTE_MIN_LENGTH = 50;
const pasteFieldId = useId();
const pasteHintId = useId();
const pasteLength = computed(() => pasteBody.value.trim().length);
const pasteValid = computed(() => pasteLength.value >= PASTE_MIN_LENGTH);

function openPasteDialog(reference: StudioReference): void {
    pasteTarget.value = reference;
    pasteBody.value = '';
    pasteOpen.value = true;
}

async function confirmPaste(): Promise<void> {
    if (!pasteTarget.value) {
        return;
    }

    if (!pasteValid.value) {
        toast.error(
            `Paste at least ${PASTE_MIN_LENGTH} characters — ${pasteLength.value} so far.`,
        );

        return;
    }

    try {
        await pasteText.mutateAsync({
            uuid: pasteTarget.value.uuid,
            text: pasteBody.value,
        });
    } catch {
        return;
    }

    pasteOpen.value = false;
    pasteTarget.value = null;
}

const confirmDeleteOpen = ref(false);
const pendingDelete = ref<StudioPosting | null>(null);

function requestDelete(posting: StudioPosting): void {
    pendingDelete.value = posting;
    confirmDeleteOpen.value = true;
}

async function confirmDelete(): Promise<void> {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deletePosting.mutateAsync(pendingDelete.value.uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    pendingDelete.value = null;
}

const confirmRestoreOpen = ref(false);
const pendingRestore = ref<StudioPosting | null>(null);

function requestRestore(posting: StudioPosting): void {
    pendingRestore.value = posting;
    confirmRestoreOpen.value = true;
}

async function confirmRestore(): Promise<void> {
    if (!pendingRestore.value) {
        return;
    }

    try {
        await restorePosting.mutateAsync(pendingRestore.value.uuid);
    } catch {
        return;
    }

    confirmRestoreOpen.value = false;
    pendingRestore.value = null;
}

const confirmBulkDeleteOpen = ref(false);

async function confirmBulkDelete(): Promise<void> {
    try {
        await bulkDeletePostings.mutateAsync(
            selectedActive.value.map((posting) => posting.uuid),
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
        await bulkRestorePostings.mutateAsync(
            selectedDeleted.value.map((posting) => posting.uuid),
        );
    } catch {
        return;
    }

    selection.value = [];
    confirmBulkRestoreOpen.value = false;
}

function startRun(): void {
    router.post(storeRun.url());
}
</script>

<template>
    <Head title="CV Studio · Postings" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Postings</h1>
            <p class="text-sm text-muted-foreground">
                {{ meta.total }}
                {{ meta.total === 1 ? 'posting' : 'postings' }} found. Fit and
                opportunity stay separate — the threshold decides entry, the
                product decides order.
            </p>
        </header>

        <div class="flex gap-2" role="tablist" aria-label="Posting sources">
            <Button
                role="tab"
                :aria-selected="tab === 'postings'"
                :variant="tab === 'postings' ? 'default' : 'outline'"
                @click="tab = 'postings'"
            >
                Scored postings
            </Button>
            <Button
                role="tab"
                :aria-selected="tab === 'manual'"
                :variant="tab === 'manual' ? 'default' : 'outline'"
                @click="tab = 'manual'"
            >
                Open manually ({{ referencesTotal }})
            </Button>
        </div>

        <template v-if="tab === 'postings'">
            <DataTableToolbar
                :selected-count="selection.length"
                selection-label="selected"
            >
                <template #search>
                    <DataTableSearch
                        v-model="searchTerm"
                        placeholder="Search title, employer or location…"
                        aria-label="Search postings"
                    />
                </template>

                <template #filters>
                    <DataTableDateRangeFilter
                        v-model="dateRange"
                        placeholder="Found any time"
                        presets
                        disable-future
                    />

                    <FilterSelect
                        class="w-44"
                        :options="scopeOptions"
                        placeholder="Any scope"
                        :model-value="filters.remote_scope || null"
                        @update:model-value="onScopeChange"
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
                        :can-delete="can('BULK_DELETE_STUDIO_POSTINGS')"
                        :can-restore="can('BULK_RESTORE_STUDIO_POSTINGS')"
                        :busy="
                            bulkDeletePostings.isLoading.value ||
                            bulkRestorePostings.isLoading.value
                        "
                        @bulk-delete="confirmBulkDeleteOpen = true"
                        @bulk-restore="confirmBulkRestoreOpen = true"
                    />
                </template>

                <template #actions>
                    <PermissionGuard permission="EXPORT_STUDIO_POSTINGS">
                        <DataTableExportMenu
                            :endpoint="exportEndpoint"
                            :params="exportParams"
                            :formats="['xlsx', 'csv', 'pdf']"
                        />
                    </PermissionGuard>

                    <PermissionGuard permission="CREATE_STUDIO_POSTINGS">
                        <Button @click="startRun">
                            <PlusIcon class="size-4" aria-hidden="true" />
                            Start run
                        </Button>
                    </PermissionGuard>
                </template>
            </DataTableToolbar>

            <div class="flex flex-col">
                <DataTable
                    v-model:selection="selection"
                    :rows="postings"
                    :columns="columns"
                    :loading="isLoading"
                    :row-class="rowClass"
                    selectable
                    caption="Discovered postings with fit scores"
                    empty-title="No postings yet"
                    empty-description="Start a discovery run to collect remote postings."
                    class="rounded-b-none border-b-0"
                >
                    <template #[`cell:title`]="{ row }">
                        <span class="truncate font-medium">{{ row.title }}</span>
                    </template>

                    <template #[`cell:employer`]="{ row }">
                        {{ row.employer_name ?? '—' }}
                    </template>

                    <template #[`cell:scope`]="{ row }">
                        {{ remoteScopeLabel(row.remote_scope) }}
                    </template>

                    <template #[`cell:fit`]="{ row }">
                        <FitScoreBadge
                            :score="row.total_score"
                            :band="row.band"
                        />
                    </template>

                    <template #[`cell:status`]="{ row }">
                        <PostingStatusBadge
                            :status="row.status"
                            :deleted-at="row.deleted_at"
                        />
                    </template>

                    <template #actions="{ row }">
                        <PermissionGuard permission="VIEW_STUDIO_POSTINGS">
                            <DataTableRowAction
                                :icon="EyeIcon"
                                :label="`View ${row.title}`"
                                tooltip="View"
                                :href="show.url(row.uuid)"
                                prefetch
                            />
                        </PermissionGuard>

                        <template v-if="!row.deleted_at">
                            <PermissionGuard permission="DELETE_STUDIO_POSTINGS">
                                <DataTableRowAction
                                    :icon="Trash2Icon"
                                    :label="`Suspend ${row.title}`"
                                    tooltip="Suspend"
                                    destructive
                                    @click="requestDelete(row)"
                                />
                            </PermissionGuard>
                        </template>

                        <PermissionGuard v-else permission="RESTORE_STUDIO_POSTINGS">
                            <DataTableRowAction
                                :icon="RotateCcwIcon"
                                :label="`Restore ${row.title}`"
                                tooltip="Restore"
                                @click="requestRestore(row)"
                            />
                        </PermissionGuard>
                    </template>
                </DataTable>

                <Paginator
                    v-model:page="page"
                    :meta="meta"
                    :disabled="isLoading"
                    label="Postings"
                    class="rounded-b-xl border border-border bg-card"
                />
            </div>
        </template>

        <template v-else>
            <p class="text-sm text-muted-foreground">
                Link-only signals — open each in your own browser, then paste
                the text you read to score it like any other posting.
            </p>

            <ul class="flex flex-col gap-2">
                <li
                    v-for="reference in references"
                    :key="reference.uuid"
                    class="flex items-center justify-between gap-3 rounded-xl border border-border bg-card p-3"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ reference.title }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ reference.canonical_url }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openPasteDialog(reference)"
                    >
                        <LinkIcon class="size-4" aria-hidden="true" />
                        Paste JD
                    </Button>
                </li>
            </ul>

            <div
                v-if="referencesPageCount > 1"
                class="flex items-center justify-between gap-3"
            >
                <p class="text-xs text-muted-foreground" aria-live="polite">
                    Page {{ referencesPage }} of {{ referencesPageCount }} ·
                    {{ referencesTotal }}
                    {{ referencesTotal === 1 ? 'link' : 'links' }}
                </p>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="referencesPage <= 1 || referencesLoading"
                        aria-label="Previous references page"
                        @click="referencesPage -= 1"
                    >
                        <ChevronLeftIcon class="size-4" aria-hidden="true" />
                        Prev
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="
                            referencesPage >= referencesPageCount ||
                            referencesLoading
                        "
                        aria-label="Next references page"
                        @click="referencesPage += 1"
                    >
                        Next
                        <ChevronRightIcon class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </template>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Suspend this posting?"
        description="It leaves the apply list but stays in market statistics."
        confirm-label="Suspend"
        @confirm="confirmDelete"
    />

    <ConfirmModal
        v-model:open="confirmRestoreOpen"
        title="Restore this posting?"
        description="It returns to the apply list."
        confirm-label="Restore"
        @confirm="confirmRestore"
    />

    <ConfirmModal
        v-model:open="confirmBulkDeleteOpen"
        destructive
        :title="`Suspend ${selectedActive.length} postings?`"
        description="They leave the apply list but stay in market statistics."
        confirm-label="Suspend"
        @confirm="confirmBulkDelete"
    />

    <ConfirmModal
        v-model:open="confirmBulkRestoreOpen"
        :title="`Restore ${selectedDeleted.length} postings?`"
        description="They return to the apply list."
        confirm-label="Restore"
        @confirm="confirmBulkRestore"
    />

    <ConfirmModal
        v-model:open="pasteOpen"
        title="Paste the posting text"
        description="Paste exactly what you read (minimum 50 characters). It is scored like any other posting and marked as supplied by you — no request is ever made to the original site."
        confirm-label="Save and score"
        @confirm="confirmPaste"
    >
        <div class="flex flex-col gap-2">
            <Label :for="pasteFieldId">Posting text</Label>
            <Textarea
                :id="pasteFieldId"
                v-model="pasteBody"
                rows="10"
                placeholder="Paste the full posting text here…"
                :aria-describedby="pasteHintId"
                :aria-invalid="!pasteValid"
            />
            <p
                :id="pasteHintId"
                class="text-xs"
                :class="
                    pasteValid ? 'text-muted-foreground' : 'text-destructive'
                "
            >
                {{ pasteLength }} / {{ PASTE_MIN_LENGTH }} characters minimum.
            </p>
        </div>
    </ConfirmModal>
</template>
