import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/roles';
import { buildRoleListQueryParams } from '../helpers/buildRoleQueryParams';
import type { RoleFilters, RolePage, RoleQueryParams } from '../types';

/** The cache key every role write invalidates. */
export const ROLES_KEY = ['roles'];

export function defaultRoleFilters(): RoleFilters {
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
 * The role list, in the one shape the admin page needs.
 *
 * `GET /roles` is the same route the Inertia page renders from —
 * `RoleController::index` branches on `expectsJson()` and returns the paginator
 * as JSON, which is what `httpJson` asks for. There is no separate
 * `/data/admin` surface on this module.
 *
 * A plain composable, not `defineQuery`: `roles/Index.vue` is the only caller,
 * so there is nothing to de-synchronise by giving it its own local `filters`
 * ref — see the `defineQuery`/`defineMutation` decision rule in
 * `FRONTEND/SKILL.md` §6.
 */
export function useRoles() {
    const filters = ref<RoleFilters>(defaultRoleFilters());

    /**
     * One builder for the list query AND the export URL (`roles/Index.vue`
     * reuses the filter half), so the two can never drift apart. `page` /
     * `per_page` ride along here; the export drops them by using the filter
     * half only.
     */
    const queryParams = computed<RoleQueryParams>(() =>
        buildRoleListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<RolePage>({
        key: () => [...ROLES_KEY, { ...queryParams.value }],
        query: () =>
            httpJson<RolePage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const roles = computed(() => data.value?.data ?? []);

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

    return { ...query, data, roles, meta, filters };
}
