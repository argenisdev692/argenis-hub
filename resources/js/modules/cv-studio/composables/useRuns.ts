import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { computed, onWatcherCleanup, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { report, show, store } from '@/routes/cv-studio/runs';
import type { StudioRun, StudioRunTerminalStatus } from '../types';

export const STUDIO_RUNS_KEY = ['studio-runs'];

const TERMINAL_STATUSES: readonly StudioRunTerminalStatus[] = [
    'finished',
    'failed',
];

export function isTerminalRunStatus(
    status: string,
): status is StudioRunTerminalStatus {
    return (TERMINAL_STATUSES as readonly string[]).includes(status);
}

/**
 * Polled run status: a 3s refetch while the run is not finished/failed.
 *
 * The watcher tracks the fetched payload (a new object on every response),
 * not `run.status` — a status that stays `harvesting` across two polls would
 * not re-trigger a status watcher, and the page would freeze mid-run. The
 * timer is cleared with `onWatcherCleanup` on every new payload and unmount.
 */
export function useRunStatus(uuid: string) {
    const { data, refetch, ...query } = useQuery<{ run: StudioRun }>({
        key: () => [...STUDIO_RUNS_KEY, uuid],
        query: () => httpJson<{ run: StudioRun }>(toUrl(show(uuid))),
        staleTime: 1000 * 2,
        gcTime: 1000 * 60 * 5,
    });

    const run = computed(() => data.value?.run);

    watch(
        data,
        (payload) => {
            const status = payload?.run.status;

            if (!status || isTerminalRunStatus(status)) {
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
