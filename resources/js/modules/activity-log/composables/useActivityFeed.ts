import { useQuery } from '@pinia/colada';
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { computed, watch } from 'vue';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/activity-logs';
import type { ActivityLogPage, ActivityLogRow } from '../types';

/** How many entries the dashboard ticker carries. */
const FEED_SIZE = 12;

/** How often the ticker pulls a fresh slice, in ms. */
const POLL_MS = 30_000;

/**
 * The newest slice of the audit trail for the dashboard "marquesinha".
 *
 * Reuses `GET /activity-logs` (JSON) rather than a bespoke endpoint — the trail
 * has no lighter read, and a 12-row page is already cheap. Pinia Colada owns
 * the cache; a visibility-gated interval keeps it live without a websocket and
 * without polling a backgrounded tab.
 */
export function useActivityFeed() {
    const { data, refetch, ...query } = useQuery<ActivityLogPage>({
        key: () => ['activity-logs', 'feed', FEED_SIZE],
        query: () =>
            httpJson<ActivityLogPage>(
                index.url({
                    query: {
                        per_page: FEED_SIZE,
                        sort_direction: 'desc',
                    },
                }),
            ),
        staleTime: POLL_MS,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const entries = computed<ActivityLogRow[]>(() =>
        (data.value?.data ?? []).map((entry) => ({
            ...entry,
            uuid: String(entry.id),
        })),
    );

    // Poll only while the tab is visible; refetch once on the way back so a
    // returning user never reads a stale strip.
    const visibility = useDocumentVisibility();
    const poll = useIntervalFn(() => void refetch(), POLL_MS, {
        immediate: false,
    });

    watch(
        visibility,
        (state) => {
            if (state === 'visible') {
                void refetch();
                poll.resume();
            } else {
                poll.pause();
            }
        },
        { immediate: true },
    );

    return { ...query, data, entries };
}
