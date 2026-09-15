import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/permissions';
import { buildPermissionListQueryParams } from '../helpers/buildPermissionQueryParams';
import type {
    PermissionFilters,
    PermissionPage,
    PermissionQueryParams,
} from '../types';

/** The cache key every permission write invalidates. */
export const PERMISSIONS_KEY = ['permission-catalog'];

/**
 * Named for the catalogue, not the entity, to keep it distinct from
 * `@/composables/usePermissions` — which answers a different question ("what
 * may the *current user* do?") and is imported by nearly every screen. Two
 * `usePermissions` in one import block is a rename waiting to go wrong.
 *
 * The cache key follows the same reasoning: `['permissions']` would read as the
 * viewer's grants rather than the managed records.
 */
export function defaultPermissionFilters(): PermissionFilters {
    return {
        search: '',
        status: 'active',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The permission catalogue, paginated for the admin table.
 *
 * Same transport as {@see useRoles}: `PermissionController::index` serves the
 * paginator as JSON when the request asks for it, so there is one route behind
 * both the Inertia page and this query.
 */
export function usePermissionCatalog() {
    const filters = ref<PermissionFilters>(defaultPermissionFilters());

    /**
     * One builder for the catalogue query AND the export URL
     * (`permissions/Index.vue` reuses the filter half), so the two can never
     * drift apart. `page` / `per_page` ride along here; the export drops them
     * by using the filter half only.
     */
    const queryParams = computed<PermissionQueryParams>(() =>
        buildPermissionListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<PermissionPage>({
        key: () => [...PERMISSIONS_KEY, { ...queryParams.value }],
        query: () =>
            httpJson<PermissionPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const permissions = computed(() => data.value?.data ?? []);

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

    return { ...query, data, permissions, meta, filters };
}
