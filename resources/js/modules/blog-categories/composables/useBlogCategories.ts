import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/blog-categories';
import type { BlogCategoryFilters, BlogCategoryPage } from '../types';

/** The key every blog-category mutation invalidates. */
export const BLOG_CATEGORIES_KEY = ['blog-categories'];

export function defaultBlogCategoryFilters(): BlogCategoryFilters {
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
 * The blog categories list, in the one shape the admin page needs.
 *
 * `GET /blog-categories` serves both the Inertia page and this query —
 * `index()` branches on `expectsJson()`. Reading it over XHR rather than off
 * the Inertia `blogCategories` prop is what buys the table `placeholderData`
 * (no empty-table flash while a filter change loads) and a cache the mutations
 * can invalidate, which a page prop cannot offer without a full visit per
 * keystroke.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref —
 * see the `defineQuery`/`defineMutation` decision rule in `FRONTEND/SKILL.md`.
 */
export function useBlogCategories() {
    const filters = ref<BlogCategoryFilters>(defaultBlogCategoryFilters());

    /**
     * An empty search or an unset date bound is dropped rather than sent as an
     * empty string: `BlogCategoryFilterData` treats `null` as "no filter", and
     * keeping the params out entirely also keeps the query key — and the URL,
     * via `useUrlSyncedFilters` — free of noise that means nothing.
     *
     * `status` is always sent. Unlike the other modules there is no "all"
     * option to translate away here, because the backend cannot serve one
     * (see `BlogCategoryStatusFilter`).
     */
    const queryParams = computed(() => ({
        ...filters.value,
        search: filters.value.search || undefined,
        date_from: filters.value.date_from ?? undefined,
        date_to: filters.value.date_to ?? undefined,
    }));

    const { data, ...query } = useQuery<BlogCategoryPage>({
        key: () => ['blog-categories', { ...queryParams.value }],
        query: () =>
            httpJson<BlogCategoryPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no
        // empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const blogCategories = computed(() => data.value?.data ?? []);
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

    return { ...query, data, blogCategories, meta, filters };
}
