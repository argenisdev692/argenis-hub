import { useQuery } from '@pinia/colada';
import { httpJson } from '@/lib/http';
import { show } from '@/routes/video-edits/admin';
import { isActiveStatus } from '../helpers/videoEditPresentation';
import type { VideoEditDetail } from '../types';
import { usePollWhile } from './usePollWhile';
import { VIDEO_EDITS_KEY } from './useVideoEdits';

/**
 * One edit in full — sources, summary, cuts, decisions and what the owner may
 * do next (`can_retry` / `can_delete` / `can_download` are decided server-side).
 * Keyed under `VIDEO_EDITS_KEY` so every mutation's invalidation reaches it.
 */
export function useVideoEdit(uuid: () => string) {
    const query = useQuery<VideoEditDetail>({
        key: () => [...VIDEO_EDITS_KEY, 'detail', uuid()],
        query: () => httpJson<VideoEditDetail>(show.url(uuid())),
        staleTime: 1000 * 30,
        gcTime: 1000 * 60 * 5,
    });

    usePollWhile(
        () =>
            query.data.value ? isActiveStatus(query.data.value.status) : false,
        query.refetch,
        3000,
    );

    return query;
}
