import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/posts';
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
     * `PostFilterData::status` only accepts `draft`, `published`, `scheduled`
     * or `suspended` — there is no `'all'` case, so the UI's "All" option is
     * sent as an omitted param. Empty search, unset category and unset date
     * bounds drop the same way, keeping both the query key and the URL (via
     * `useUrlSyncedFilters`) free of noise that means nothing.
     */
    const queryParams = computed(() => ({
        ...filters.value,
        status:
            filters.value.status === 'all' ? undefined : filters.value.status,
        search: filters.value.search || undefined,
        category_uuid: filters.value.category_uuid ?? undefined,
        date_from: filters.value.date_from ?? undefined,
        date_to: filters.value.date_to ?? undefined,
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
