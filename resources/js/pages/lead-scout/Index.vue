<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { EyeIcon, Settings2Icon, WalletIcon } from '@lucide/vue';
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
    DataTable,
    DataTableDateRangeFilter,
    DataTableSearch,
    DataTableToolbar,
    Paginator,
} from '@/common/table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import LeadTierBadge from '@/modules/lead-scout/components/LeadTierBadge.vue';
import { useLeadMutations } from '@/modules/lead-scout/composables/useLeadMutations';
import { useLeads } from '@/modules/lead-scout/composables/useLeads';
import { useAiSettings, useBudgets } from '@/modules/lead-scout/composables/useSettings';
import {
    LEAD_ORIGIN_OPTIONS,
    LEAD_TIER_OPTIONS,
    LEAD_TYPE_OPTIONS,
} from '@/modules/lead-scout/helpers/leadPresentation';
import type { LeadListItem } from '@/modules/lead-scout/types';
import { exportMethod, index, show } from '@/routes/lead-scout/leads';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'LeadScout', href: index() }],
    },
});

const { leads, meta: queryMeta, filters, isLoading } = useLeads();
const { updateBudgets, updateAiSettings } = useLeadMutations();
const { settings: aiSettings } = useAiSettings();
const { budgets } = useBudgets();

// NOTE: filters stay in-memory on purpose — `useUrlSyncedFilters` only
// supports scalar values, and the tier/type/origin facets are arrays.
// The export buttons reuse the same builder, so exports always match.

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

const columns: DataTableColumn<LeadListItem>[] = [
    { key: 'name', header: 'Company', value: (row) => row.name },
    {
        key: 'domain',
        header: 'Domain',
        value: (row) => row.domain,
        hideOnMobile: true,
    },
    { key: 'country', header: 'Country', value: (row) => row.country ?? '—' },
    {
        key: 'tier',
        header: 'Tier',
        value: (row) => row.tier ?? 'Unscored',
    },
    {
        key: 'lead_score',
        header: 'Score',
        value: (row) => (row.lead_score === null ? '—' : String(row.lead_score)),
        align: 'right',
    },
    {
        key: 'needs_research',
        header: 'Research',
        value: (row) => (row.needs_research ? 'Investigate' : '—'),
        hideOnMobile: true,
    },
];

const searchTerm = computed<string>({
    get: () => filters.value.search,
    set: (value) => {
        filters.value.search = value;
        filters.value.page = 1;
    },
});

function multiModel(key: 'tier' | 'country' | 'company_type' | 'origin') {
    return computed<string[]>({
        get: () => filters.value[key],
        set: (value) => {
            filters.value[key] = value;
            filters.value.page = 1;
        },
    });
}

const tierModel = multiModel('tier');
const typeModel = multiModel('company_type');
const originModel = multiModel('origin');

const dateRange = computed<DateRange>({
    get: () => ({ from: filters.value.date_from, to: filters.value.date_to }),
    set: (value) => {
        filters.value.date_from = value.from;
        filters.value.date_to = value.to;
        filters.value.page = 1;
    },
});

type FilterSelectModel =
    FilterSelectOption['value'] | FilterSelectOption['value'][] | null;

function facetValues(value: FilterSelectModel): string[] {
    if (Array.isArray(value)) {
        return value.map((entry) => String(entry));
    }

    return value === null ? [] : [String(value)];
}

/** Export URL with repeated array keys (`tier[]=A&tier[]=B`), never comma-joined. */
function exportUrl(dataset: 'leads' | 'funnel', format: 'xlsx' | 'csv' | 'pdf'): string {
    const query = new URLSearchParams({ dataset, format });
    query.set('page', '1');
    query.set('per_page', '100');

    const appendAll = (key: string, values: string[]): void => {
        for (const value of values) {
            query.append(`${key}[]`, value);
        }
    };

    appendAll('tier', filters.value.tier);
    appendAll('country', filters.value.country);
    appendAll('company_type', filters.value.company_type);
    appendAll('origin', filters.value.origin);

    if (filters.value.search.trim() !== '') {
        query.set('search', filters.value.search.trim());
    }

    if (filters.value.date_from !== null) {
        query.set('date_from', filters.value.date_from);
    }

    if (filters.value.date_to !== null) {
        query.set('date_to', filters.value.date_to);
    }

    return `${exportMethod.url()}?${query.toString()}`;
}

function download(dataset: 'leads' | 'funnel', format: 'xlsx' | 'csv' | 'pdf'): void {
    window.location.assign(exportUrl(dataset, format));
}

// --- Budgets dialog state (editable limits, read-only spend). ---
const budgetsOpen = ref(false);
const budgetDraft = ref<Record<string, number>>({});

function openBudgets(): void {
    budgetDraft.value = Object.fromEntries(
        (budgets.value?.categories ?? []).map((category) => [
            category.category,
            category.limit_micros / 1_000_000,
        ]),
    );
    budgetsOpen.value = true;
}

async function saveBudgets(): Promise<void> {
    await updateBudgets.mutateAsync(
        Object.entries(budgetDraft.value).map(([category, limit_eur]) => ({
            category,
            limit_eur,
        })),
    );
    budgetsOpen.value = false;
}

// --- AI settings dialog state (defaults per purpose, catalog-closed). ---
const aiOpen = ref(false);
const aiDraft = ref<Record<string, { provider: string; model: string }>>({});

function openAiSettings(): void {
    const purposes = aiSettings.value?.purposes ?? {};

    aiDraft.value = Object.fromEntries(
        Object.entries(purposes).map(([purpose, current]) => [
            purpose,
            {
                provider: current.provider ?? '',
                model: current.model ?? '',
            },
        ]),
    );
    aiOpen.value = true;
}

function aiModelsFor(purpose: string, provider: string) {
    return (
        aiSettings.value?.purposes[purpose]?.options.filter(
            (option) => option.provider === provider,
        ) ?? []
    );
}

async function saveAiSettings(purpose: string): Promise<void> {
    const draft = aiDraft.value[purpose];

    if (draft === undefined) {
        return;
    }

    await updateAiSettings.mutateAsync({
        purpose,
        provider: draft.provider,
        model: draft.model,
    });
    aiOpen.value = false;
}
</script>

<template>
    <Head title="LeadScout" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="flex-1 text-2xl font-semibold tracking-tight">
                LeadScout
            </h1>

            <PermissionGuard permission="UPDATE_LEAD_SCOUT">
                <Button
                    variant="outline"
                    size="sm"
                    @click="openBudgets"
                >
                    <WalletIcon class="size-4" aria-hidden="true" />
                    Budgets
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="openAiSettings"
                >
                    <Settings2Icon class="size-4" aria-hidden="true" />
                    AI defaults
                </Button>
            </PermissionGuard>

            <PermissionGuard permission="EXPORT_LEAD_SCOUT">
                <Button
                    variant="outline"
                    size="sm"
                    @click="download('leads', 'xlsx')"
                >
                    Export leads
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="download('funnel', 'xlsx')"
                >
                    Export funnel
                </Button>
            </PermissionGuard>
        </div>

        <DataTableToolbar>
            <DataTableSearch
                v-model="searchTerm"
                placeholder="Search company or domain…"
                :max-length="255"
            />
            <FilterSelect
                :model-value="tierModel"
                :options="LEAD_TIER_OPTIONS"
                :multiple="true"
                placeholder="Tier"
                @update:model-value="
                    (value: FilterSelectModel) => {
                        tierModel = facetValues(value);
                    }
                "
            />
            <FilterSelect
                :model-value="typeModel"
                :options="LEAD_TYPE_OPTIONS"
                :multiple="true"
                placeholder="Type"
                @update:model-value="
                    (value: FilterSelectModel) => {
                        typeModel = facetValues(value);
                    }
                "
            />
            <FilterSelect
                :model-value="originModel"
                :options="LEAD_ORIGIN_OPTIONS"
                :multiple="true"
                placeholder="Origin"
                @update:model-value="
                    (value: FilterSelectModel) => {
                        originModel = facetValues(value);
                    }
                "
            />
            <DataTableDateRangeFilter v-model="dateRange" />
        </DataTableToolbar>

        <DataTable
            :rows="leads"
            :columns="columns"
            :loading="isLoading"
            caption="Prioritized lead bandeja"
            empty-title="No leads yet"
            empty-description="Import the ICP list or wait for the next discovery run."
        >
            <template #cell:tier="{ row }">
                <LeadTierBadge :tier="row.tier" />
            </template>
            <template #actions="{ row }">
                <PermissionGuard permission="VIEW_LEAD_SCOUT">
                    <Button
                        as-child
                        variant="ghost"
                        size="icon"
                        :aria-label="`Open ${row.name}`"
                    >
                        <Link :href="show.url(row.uuid)">
                            <EyeIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </PermissionGuard>
            </template>
        </DataTable>

        <Paginator
            v-if="meta.total > 0"
            v-model:page="filters.page"
            v-model:per-page="filters.per_page"
            :meta="meta"
            label="leads"
        />

        <Dialog v-model:open="budgetsOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Monthly budgets (EUR)</DialogTitle>
                    <DialogDescription>
                        Limits stop paid calls; free sources continue.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-4">
                    <div
                        v-for="category in budgets?.categories ?? []"
                        :key="category.category"
                        class="flex flex-col gap-2"
                    >
                        <Label :for="`budget-${category.category}`">
                            {{ category.category }} — spent
                            {{ (category.spent_micros / 1_000_000).toFixed(2) }}
                            ({{ category.percent }}%)
                        </Label>
                        <Input
                            :id="`budget-${category.category}`"
                            v-model.number="budgetDraft[category.category]"
                            type="number"
                            min="0"
                            step="1"
                        />
                    </div>
                    <Button
                        :disabled="updateBudgets.isLoading.value"
                        @click="saveBudgets"
                    >
                        Save limits
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="aiOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>AI defaults per purpose</DialogTitle>
                    <DialogDescription>
                        Closed catalog — changing a default never rewrites
                        existing drafts.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-6">
                    <div
                        v-for="(current, purpose) in aiSettings?.purposes ?? {}"
                        :key="purpose"
                        class="flex flex-col gap-2"
                    >
                        <p class="text-sm font-medium capitalize">
                            {{ purpose }}
                        </p>
                        <Select
                            :model-value="aiDraft[purpose]?.provider ?? ''"
                            @update:model-value="
                                (value) => {
                                    if (aiDraft[purpose]) {
                                        aiDraft[purpose].provider = String(value);
                                        aiDraft[purpose].model =
                                            aiModelsFor(purpose, String(value))[0]?.model ?? '';
                                    }
                                }
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Provider" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in current.options"
                                    :key="`${option.provider}-${option.model}`"
                                    :value="option.provider"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            :model-value="aiDraft[purpose]?.model ?? ''"
                            @update:model-value="
                                (value) => {
                                    if (aiDraft[purpose]) {
                                        aiDraft[purpose].model = String(value);
                                    }
                                }
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Model" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in aiModelsFor(
                                        purpose,
                                        aiDraft[purpose]?.provider ?? '',
                                    )"
                                    :key="option.model"
                                    :value="option.model"
                                    :disabled="!option.available"
                                >
                                    {{ option.label }} ·
                                    ${{ option.est_cost_per_100_usd.toFixed(4) }}/100
                                    {{ option.available ? '' : '(no key)' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="updateAiSettings.isLoading.value"
                            @click="saveAiSettings(purpose)"
                        >
                            Save {{ purpose }}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
