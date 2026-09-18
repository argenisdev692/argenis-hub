import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { computed, onWatcherCleanup, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { report, show, store } from '@/routes/cv-studio/runs';
import type { StudioRun } from '../types';

export const STUDIO_RUNS_KEY = ['studio-runs'];

const ACTIVE_STATUSES = ['queued', 'harvesting', 'harvested', 'extracted'];

/**
 * Run start + polled status. A 3s interval refetches while the run is
 * active and stops when it finishes; the timer is cleaned up with
 * `onWatcherCleanup`, and the report query stays disabled until then, so a
 * zero-match run still produces its report (SC-1) without extra requests.
 */
export function useRunStatus(uuid: string) {
    const { data, refetch, ...query } = useQuery<{ run: StudioRun }>({
        key: () => ['studio-runs', uuid],
        query: () => httpJson<{ run: StudioRun }>(toUrl(show(uuid))),
        staleTime: 1000 * 5,
        gcTime: 1000 * 60 * 5,
    });

    const run = computed(() => data.value?.run);

    watch(
        () => run.value?.status,
        (status) => {
            if (!status || !ACTIVE_STATUSES.includes(status)) {
                return;
            }

            const timer = setTimeout(() => void refetch(), 3000);

            onWatcherCleanup(() => {
                clearTimeout(timer);
            });
        },
        { immediate: true },
    );

    return { ...query, refetch, run };
}

export function useStartRun() {
    const queryCache = useQueryCache();
    const lastUuid = ref<string | null>(null);

    const startRun = useMutation({
        mutation: async (profileUuid: string) => {
            const response = await httpJson<{ data: { uuid: string } }>(
                toUrl(store()),
                { method: 'POST', body: { profile_uuid: profileUuid } },
            );

            lastUuid.value = response.data.uuid;

            return response.data.uuid;
        },
        onSuccess() {
            toast.success('Discovery run queued.');
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to start the run.',
            );
        },
        onSettled: async () =>
            await queryCache.invalidateQueries({ key: STUDIO_RUNS_KEY }),
    });

    return { startRun, lastUuid };
}

export function useInsightReport(uuid: string, enabled: boolean) {
    const { data, ...query } = useQuery<{ report: Record<string, unknown> }>({
        key: () => ['studio-runs', uuid, 'report'],
        query: () => httpJson<{ report: Record<string, unknown> }>(toUrl(report(uuid))),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
        enabled,
    });

    return { ...query, report: computed(() => data.value?.report) };
}
