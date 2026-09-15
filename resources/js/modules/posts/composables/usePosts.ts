import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/posts';
import { buildPostQueryParams } from '../helpers/buildPostQueryParams';
import type { PostFilters, PostPage } from '../types';

export function defaultPostFilters(): PostFilters {
    return {
        search: '',
        status: 'all',
        category_uuid: null,
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}

/**
 * The Posts list, in the one shape the admin page needs.
 *
 * `GET /posts` serves both the Inertia page and this query — `index()` branches
 * on `expectsJson()`. Reading it over XHR rather than off the Inertia `posts`
 * prop is what buys the table `placeholderData` (no empty-table flash while a
 * filter change loads) and a cache the mutations can invalidate, which a page
 * prop cannot offer without a full visit per keystroke.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref —
 * see the `defineQuery`/`defineMutation` decision rule in `FRONTEND/SKILL.md`.
 */
export function usePosts() {
    const filters = ref<PostFilters>(defaultPostFilters());

    /**
     * Filter half shared with the export menu — see `buildPostQueryParams` —
     * plus pagination, which is query-only and must never reach an export URL.
     */
    const queryParams = computed(() => ({
        ...buildPostQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<PostPage>({
        key: () => ['posts', { ...queryParams.value }],
        query: () =>
            httpJson<PostPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no
        // empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const posts = computed(() => data.value?.data ?? []);
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

    return { ...query, data, posts, meta, filters };
}
