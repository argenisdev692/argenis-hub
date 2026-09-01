import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/campaigns';
import type { CampaignFilters, CampaignPage } from '../types';

export function defaultCampaignFilters(): CampaignFilters {
    return {
        search: '',
        status: 'all',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The query string `GET /campaigns` actually reads, derived from the UI filter
 * state.
 *
 * Exported because the export endpoint takes the SAME filter DTO
 * (`CampaignExportController` type-hints `CampaignFilterData`), so the download
 * must carry the identical params or it silently exports a different set than
 * the one on screen. One builder, two callers — the drift this prevents is
 * invisible until someone opens the spreadsheet.
 *
 * `'all'` is sent as an omitted param rather than the literal string:
 * `scopeApplyFilters()` matches `status` against the six lifecycle values and
 * the repository checks it for `'suspended'`, so anything else is already a
 * no-op — but omitting it keeps the query key (and the URL, via
 * `useUrlSyncedFilters`) short.
 */
export function buildCampaignQueryParams(
    filters: CampaignFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/**
 * The campaign list, in the one shape the admin page needs.
 *
 * `GET /campaigns` serves both the Inertia page and this query —
 * `CampaignController::index()` branches on `expectsJson()`. Reading it over
 * XHR rather than off the Inertia `campaigns` prop is what buys the table
 * `placeholderData` (no empty-table flash while a filter change loads) and a
 * cache the mutations can invalidate, which a page prop cannot offer without a
 * full visit per keystroke.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref —
 * see the `defineQuery`/`defineMutation` decision rule in `FRONTEND/SKILL.md`
 * §6.
 *
 * There is no sort axis on purpose: `EloquentCampaignRepository::paginate()`
 * hard-codes `orderByDesc('created_at')`, so a sortable column header would be
 * a control that does nothing.
 */
export function useCampaigns() {
    const filters = ref<CampaignFilters>(defaultCampaignFilters());

    const queryParams = computed(() => ({
        ...buildCampaignQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<CampaignPage>({
        key: () => ['campaigns', { ...queryParams.value }],
        query: () =>
            httpJson<CampaignPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no
        // empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const campaigns = computed(() => data.value?.data ?? []);

    const meta = computed<PaginationMeta | undefined>(() =>
        data.value
            ? {
                  current_page: data.value.current_page,
                  last_page: data.value.last_page,
                  per_page: data.value.per_page,
                  from: data.value.from,
                  to: data.value.to,
                  total: data.value.total,
              }
            : undefined,
    );

    return { ...query, data, campaigns, meta, filters };
}
