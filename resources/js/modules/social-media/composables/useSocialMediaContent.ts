import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/social-media';
import type {
    SocialMediaContentFilters,
    SocialMediaContentPage,
} from '../types';

export function defaultSocialMediaContentFilters(): SocialMediaContentFilters {
    return {
        search: '',
        status: 'all',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The query string `GET /social-media` actually reads, derived from the UI
 * filter state.
 *
 * Exported because the export endpoint takes the SAME filter DTO
 * (`SocialMediaContentExportController` type-hints
 * `SocialMediaContentFilterData`), so the download must carry the identical
 * params or it silently exports a different set than the one on screen. One
 * builder, two callers — the drift this prevents is invisible until someone
 * opens the spreadsheet.
 *
 * `'all'` is sent as an omitted param rather than the literal string:
 * `scopeApplyFilters()` matches `status` against the six lifecycle values and
 * the repository checks it for `'suspended'`, so anything else is already a
 * no-op — but omitting it keeps the query key (and the URL, via
 * `useUrlSyncedFilters`) short.
 */
export function buildSocialMediaContentQueryParams(
    filters: SocialMediaContentFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/**
 * The social media content list, in the one shape the admin page needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters`
 * ref — see the `defineQuery`/`defineMutation` decision rule in
 * `FRONTEND/SKILL.md` §6.
 *
 * There is no sort axis on purpose: `EloquentSocialMediaContentRepository::
 * paginate()` hard-codes `orderByDesc('created_at')`, so a sortable column
 * header would be a control that does nothing.
 */
export function useSocialMediaContent() {
    const filters = ref<SocialMediaContentFilters>(
        defaultSocialMediaContentFilters(),
    );

    const queryParams = computed(() => ({
        ...buildSocialMediaContentQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<SocialMediaContentPage>({
        key: () => ['social-media-content', { ...queryParams.value }],
        query: () =>
            httpJson<SocialMediaContentPage>(
                index.url({ query: queryParams.value }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const content = computed(() => data.value?.data ?? []);

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

    return { ...query, data, content, meta, filters };
}
