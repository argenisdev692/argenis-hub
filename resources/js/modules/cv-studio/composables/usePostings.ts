import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/cv-studio/postings';
import { buildPostingListQueryParams } from '../helpers/buildPostingQueryParams';
import type { StudioPostingFilters, StudioPostingPage } from '../types';

/** The key every posting mutation invalidates. */
export const STUDIO_POSTINGS_KEY = ['studio-postings'];

export const POSTINGS_PER_PAGE_OPTIONS = [15, 30, 50] as const;

export function defaultPostingFilters(): StudioPostingFilters {
    return {
        search: '',
        status: 'active',
        remote_scope: '',
        stages: [],
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: POSTINGS_PER_PAGE_OPTIONS[0],
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
        key: () => [...STUDIO_POSTINGS_KEY, { ...queryParams.value }],
        query: () =>
            httpJson<StudioPostingPage>(
                index.url({ query: queryParams.value }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const postings = computed(() => data.value?.data ?? []);
    const meta = computed<PaginationMeta>(() => ({
        current_page: data.value?.current_page ?? 1,
        last_page: data.value?.last_page ?? 1,
        per_page: data.value?.per_page ?? filters.value.per_page,
        from: data.value?.from ?? null,
        to: data.value?.to ?? null,
        total: data.value?.total ?? 0,
    }));

    return { ...query, data, postings, meta, filters };
}
