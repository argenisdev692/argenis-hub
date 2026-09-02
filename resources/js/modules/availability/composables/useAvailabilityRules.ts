import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/availability-rules';
import type { AvailabilityRuleFilters, AvailabilityRulePage } from '../types';

/** The key every weekly-rule mutation invalidates. */
export const AVAILABILITY_RULES_KEY = ['availability-rules'];

export function defaultAvailabilityRuleFilters(): AvailabilityRuleFilters {
    return {
        day_of_week: 'all',
        availability: 'all',
        status: 'active',
        page: 1,
        per_page: 15,
    };
}

/**
 * The query string `GET /availability-rules` actually reads, derived from the UI
 * filter state.
 *
 * Exported because `AvailabilityRuleExportController` type-hints the SAME
 * `AvailabilityRuleFilterData`, so the download must carry identical params or
 * it silently exports a different set than the one on screen. One builder, two
 * callers — the drift this prevents is invisible until someone opens the
 * spreadsheet.
 *
 * `'all'` is sent as an omitted param rather than the literal string:
 * `scopeApplyFilters()` compares `availability` against `available`/`unavailable`
 * and `day_of_week` against an integer, so anything else is already a no-op —
 * but omitting it keeps the query key (and the URL, via `useUrlSyncedFilters`)
 * short. `status` is always sent; unlike `availability` it has no "all" value
 * the backend could serve (see `AvailabilityStatusFilter`).
 */
export function buildAvailabilityRuleQueryParams(
    filters: AvailabilityRuleFilters,
): Record<string, string | number | undefined> {
    return {
        day_of_week:
            filters.day_of_week === 'all' ? undefined : filters.day_of_week,
        availability:
            filters.availability === 'all' ? undefined : filters.availability,
        status: filters.status,
    };
}

/**
 * The weekly-rules list, in the one shape the admin page needs.
 *
 * `GET /availability-rules` serves both the Inertia page and this query —
 * `AvailabilityRuleController::index()` branches on `expectsJson()`. Reading it
 * over XHR rather than off the Inertia `availabilityRules` prop is what buys the
 * table `placeholderData` (no empty-table flash while a filter change loads) and
 * a cache the mutations can invalidate, which a page prop cannot offer without a
 * full visit per keystroke.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref —
 * see the `defineQuery`/`defineMutation` decision rule in `FRONTEND/SKILL.md` §6.
 *
 * There is no sort axis on purpose: `EloquentAvailabilityRuleRepository::paginate()`
 * hard-codes `orderBy('day_of_week')->orderBy('start_time')`, which is the only
 * order a weekly template reads correctly in — a sortable header would be a
 * control that does nothing.
 */
export function useAvailabilityRules() {
    const filters = ref<AvailabilityRuleFilters>(
        defaultAvailabilityRuleFilters(),
    );

    const queryParams = computed(() => ({
        ...buildAvailabilityRuleQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<AvailabilityRulePage>({
        key: () => ['availability-rules', { ...queryParams.value }],
        query: () =>
            httpJson<AvailabilityRulePage>(
                index.url({ query: queryParams.value }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no
        // empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const availabilityRules = computed(() => data.value?.data ?? []);

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

    return { ...query, data, availabilityRules, meta, filters };
}
