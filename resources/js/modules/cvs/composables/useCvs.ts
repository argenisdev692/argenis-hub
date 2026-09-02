import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/cvs';
import { buildCvListQueryParams } from '../helpers/buildCvQueryParams';
import type { CvFilters, CvPage } from '../types';

/** The key every CV mutation invalidates. */
export const CVS_KEY = ['cvs'];

export function defaultCvFilters(): CvFilters {
    return {
        search: '',
        status: 'active',
        niche: '',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The CV list, in the one shape the admin page needs.
 *
 * `GET /cvs` serves both the Inertia page and this query — `index()` branches
 * on `expectsJson()`. Reading it over XHR rather than off the Inertia `cvs`
 * prop is what buys the table `placeholderData` (no empty-table flash while a
 * filter change loads) and a cache the mutations can invalidate, which a page
 * prop cannot offer without a full visit per keystroke.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref —
 * see the `defineQuery`/`defineMutation` decision rule in `FRONTEND/SKILL.md`.
 *
 * Every row is scoped to the authenticated owner by `scopeOwnedBy` on the
 * server: a CV is personal data, so holding `VIEW_ANY_CVS` is not by itself
 * enough to see someone else's (OWASP §11, BOLA).
 */
export function useCvs() {
    const filters = ref<CvFilters>(defaultCvFilters());

    const queryParams = computed(() => buildCvListQueryParams(filters.value));

    const { data, ...query } = useQuery<CvPage>({
        key: () => ['cvs', { ...queryParams.value }],
        query: () => httpJson<CvPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no
        // empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const cvs = computed(() => data.value?.data ?? []);
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

    return { ...query, data, cvs, meta, filters };
}
