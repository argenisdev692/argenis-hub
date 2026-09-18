import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/cv-studio/postings';
import { buildPostingListQueryParams } from '../helpers/buildPostingQueryParams';
import type { StudioPostingFilters, StudioPostingPage } from '../types';

/** The key every posting mutation invalidates. */
export const STUDIO_POSTINGS_KEY = ['studio-postings'];

export function defaultPostingFilters(): StudioPostingFilters {
    return {
        search: '',
        status: 'active',
        remote_scope: '',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The posting list. A plain composable, not `defineQuery`: `Index.vue` is
 * the only caller, so per-caller filter state cannot de-synchronise.
 */
export function usePostings() {
    const filters = ref<StudioPostingFilters>(defaultPostingFilters());

    const queryParams = computed(() =>
        buildPostingListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<StudioPostingPage>({
        key: () => ['studio-postings', { ...queryParams.value }],
        query: () => httpJson<StudioPostingPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const postings = computed(() => data.value?.data ?? []);
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

    return { ...query, data, postings, meta, filters };
}
