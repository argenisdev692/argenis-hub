import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/video-edits/admin';
import { buildVideoEditListQueryParams } from '../helpers/buildVideoEditQueryParams';
import { isActiveStatus } from '../helpers/videoEditPresentation';
import type { VideoEditFilters, VideoEditPage } from '../types';
import { usePollWhile } from './usePollWhile';

/** The key every video edit mutation invalidates. */
export const VIDEO_EDITS_KEY = ['video-edits'];

export function defaultVideoEditFilters(): VideoEditFilters {
    return {
        search: '',
        status: '',
        mode: '',
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}

/**
 * The caller's edit history. Every row is owner-scoped on the server
 * (`scopeOwnedBy`, FR-21), so `VIEW_ANY_VIDEO_EDITS` never reveals another
 * user's recordings.
 *
 * While any visible row is queued or processing the page polls, so progress
 * and the final status land without a manual refresh.
 */
export function useVideoEdits() {
    const filters = ref<VideoEditFilters>(defaultVideoEditFilters());

    const queryParams = computed(() =>
        buildVideoEditListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<VideoEditPage>({
        key: () => [...VIDEO_EDITS_KEY, { ...queryParams.value }],
        query: () =>
            httpJson<VideoEditPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const videoEdits = computed(() => data.value?.data ?? []);

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

    usePollWhile(
        () => videoEdits.value.some((edit) => isActiveStatus(edit.status)),
        query.refetch,
    );

    return { ...query, data, videoEdits, meta, filters };
}
