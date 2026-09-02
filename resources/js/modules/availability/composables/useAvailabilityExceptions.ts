import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/availability-exceptions';
import type {
    AvailabilityExceptionFilters,
    AvailabilityExceptionPage,
} from '../types';

/** The key every date-exception mutation invalidates. */
export const AVAILABILITY_EXCEPTIONS_KEY = ['availability-exceptions'];

export function defaultAvailabilityExceptionFilters(): AvailabilityExceptionFilters {
    return {
        search: '',
        availability: 'all',
        status: 'active',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The query string `GET /availability-exceptions` actually reads, derived from
 * the UI filter state.
 *
 * Exported because `AvailabilityExceptionExportController` type-hints the SAME
 * `AvailabilityExceptionFilterData`, so the download must carry identical params
 * or it silently exports a different set than the one on screen. One builder,
 * two callers.
 *
 * An empty search or an unset date bound is dropped rather than sent as an empty
 * string: the DTO treats `null` as "no filter", and keeping the params out
 * entirely also keeps the query key — and the URL, via `useUrlSyncedFilters` —
 * free of noise that means nothing.
 */
export function buildAvailabilityExceptionQueryParams(
    filters: AvailabilityExceptionFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        availability:
            filters.availability === 'all' ? undefined : filters.availability,
        status: filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/**
 * The date-exceptions list, in the one shape the admin page needs.
 *
 * `GET /availability-exceptions` serves both the Inertia page and this query —
 * `AvailabilityExceptionController::index()` branches on `expectsJson()`.
 * Reading it over XHR rather than off the Inertia `availabilityExceptions` prop
 * is what buys the table `placeholderData` and a cache the mutations can
 * invalidate.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller.
 *
 * No sort axis — `EloquentAvailabilityExceptionRepository::paginate()` hard-codes
 * `orderBy('date')`, which is the order a list of dated overrides reads in.
 */
export function useAvailabilityExceptions() {
    const filters = ref<AvailabilityExceptionFilters>(
        defaultAvailabilityExceptionFilters(),
    );

    const queryParams = computed(() => ({
        ...buildAvailabilityExceptionQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<AvailabilityExceptionPage>({
        key: () => ['availability-exceptions', { ...queryParams.value }],
        query: () =>
            httpJson<AvailabilityExceptionPage>(
                index.url({ query: queryParams.value }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no
        // empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const availabilityExceptions = computed(() => data.value?.data ?? []);

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

    return { ...query, data, availabilityExceptions, meta, filters };
}
