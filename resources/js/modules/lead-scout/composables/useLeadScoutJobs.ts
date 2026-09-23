import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import type { MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { discover, ingest, rescore } from '@/routes/lead-scout/jobs';
import { index as sourcesIndex, run } from '@/routes/lead-scout/sources';
import type { LeadSource } from '../types';
import { LEADS_KEY } from './useLeads';

const SOURCES_KEY = ['lead-scout', 'sources'];

type QueuedResponse = { data: { queued: number } };

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

/**
 * The source registry, read only while the jobs dialog is open (`enabled`),
 * so the bandeja never pays for it.
 */
export function useLeadSources(enabled: MaybeRefOrGetter<boolean>) {
    const { data, ...query } = useQuery<LeadSource[]>({
        key: () => SOURCES_KEY,
        query: () =>
            httpJson<{ data: LeadSource[] }>(sourcesIndex().url).then(
                (response) => response.data,
            ),
        enabled: () => toValue(enabled),
        staleTime: 1000 * 30,
        gcTime: 1000 * 60 * 5,
    });

    return { ...query, sources: computed(() => data.value ?? []) };
}

/**
 * On-demand runs of the scheduled pipeline. Every endpoint only queues work
 * (202), so success means "queued", not "finished" — the bandeja refreshes
 * as the queue worker lands results.
 */
export function useLeadScoutJobs() {
    const queryCache = useQueryCache();

    const runSource = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(run(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success(
                'Source queued. Results land in the bandeja as it runs.',
            );
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to queue the source.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: SOURCES_KEY }),
    });

    const runIngest = useMutation({
        mutation: () =>
            httpJson<QueuedResponse>(toUrl(ingest()), { method: 'POST' }),
        onSuccess(response) {
            const { queued } = response.data;
            toast.success(
                queued === 0
                    ? 'No active sources to run (or all are already running).'
                    : `${queued} source${queued === 1 ? '' : 's'} queued.`,
            );
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to queue the ingest run.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: SOURCES_KEY }),
    });

    const runDiscover = useMutation({
        mutation: () =>
            httpJson<QueuedResponse>(toUrl(discover()), { method: 'POST' }),
        onSuccess() {
            toast.success('Agency discovery queued.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to queue discovery.'));
        },
    });

    const runRescore = useMutation({
        mutation: () =>
            httpJson<QueuedResponse>(toUrl(rescore()), { method: 'POST' }),
        onSuccess(response) {
            const { queued } = response.data;
            toast.success(
                `${queued} compan${queued === 1 ? 'y' : 'ies'} queued for re-scoring.`,
            );
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to queue the re-score.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    return { runSource, runIngest, runDiscover, runRescore };
}
