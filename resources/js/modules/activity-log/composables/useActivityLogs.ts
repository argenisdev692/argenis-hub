import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/activity-logs';
import { buildActivityLogQueryParams } from '../helpers/buildActivityLogQueryParams';
import { defaultActivityLogFilters } from '../schemas/activityLogFilterSchema';
import type {
    ActivityLogFilters,
    ActivityLogPage,
    ActivityLogRow,
} from '../types';

export { defaultActivityLogFilters };

/**
 * The audit trail, in the one shape the admin index needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so a
 * private `filters` ref cannot de-synchronise from anything — see the
 * `defineQuery` decision rule in `FRONTEND/SKILL.md` §6.
 *
 * `ActivityLogController::index()` answers `Accept: application/json` with
 * Laravel's flat paginator, so this reads it straight over `httpJson` rather
 * than through an Inertia visit (the page's initial `logs` prop is ignored, as
 * in the Services module).
 */
export function useActivityLogs() {
    const filters = ref<ActivityLogFilters>(defaultActivityLogFilters());

    /**
     * Filter half shared with the export menu — see
     * `buildActivityLogQueryParams` — plus pagination, which is query-only
     * and must never reach an export URL.
     */
    const queryParams = computed(() => ({
        ...buildActivityLogQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<ActivityLogPage>({
        key: () => ['activity-logs', { ...queryParams.value }],
        query: () =>
            httpJson<ActivityLogPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the current page visible while the next one loads.
        placeholderData: (previousData) => previousData,
    });

    /**
     * The shared `DataTable` keys rows by `uuid`; the trail has only a numeric
     * `id`, so derive a stable string key from it here — one place, not in the
     * template.
     */
    const rows = computed<ActivityLogRow[]>(() =>
        (data.value?.data ?? []).map((entry) => ({
            ...entry,
            uuid: String(entry.id),
        })),
    );

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

    return { ...query, data, rows, meta, filters };
}
