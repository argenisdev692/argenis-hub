import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/roles';
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
     * Empty search and unset date bounds are dropped rather than sent blank, so
     * the query key (and the URL, via `useUrlSyncedFilters`) stay clean.
     * `status` is always sent: unlike the other modules there is no "both" case
     * to express by omitting it — the backend's default is `active`.
     *
     * Typed against the generated filter DTO, so a field renamed in
     * `RoleFilterData` fails the build here instead of silently never applying.
     */
    const queryParams = computed<RoleQueryParams>(() => ({
        search: filters.value.search || undefined,
        status: filters.value.status,
        date_from: filters.value.date_from ?? undefined,
        date_to: filters.value.date_to ?? undefined,
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

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
