import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { useIntervalFn } from '@vueuse/core';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { computed, onWatcherCleanup, ref, toValue, watch } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { status as statusRoute } from '@/routes/lead-scout';
import { discover, ingest, rescore } from '@/routes/lead-scout/jobs';
import { index as sourcesIndex, run } from '@/routes/lead-scout/sources';
import type { LeadSource } from '../types';
import { LEADS_KEY } from './useLeads';

const SOURCES_KEY = ['lead-scout', 'sources'];

type QueuedResponse = { data: { queued: number } };

/**
 * Cooldowns mirror the server's per-minute budgets
 * (`throttle:lead-scout-jobs-*`): 6/min ingest → 10s, 2/min
 * discover/rescore → 30s. Without them the buttons invite a click that can
 * only come back as a 429.
 */
const INGEST_COOLDOWN_SECONDS = 10;
const DISCOVER_COOLDOWN_SECONDS = 30;
const RESCORE_COOLDOWN_SECONDS = 30;

function errorMessage(error: unknown, fallback: string): string {
    if (!(error instanceof HttpError)) {
        return fallback;
    }

    if (error.status === 429) {
        return error.retryAfter === null
            ? 'Too many requests — wait a moment and try again.'
            : `Too many requests — try again in ${error.retryAfter}s.`;
    }

    return error.message;
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
 *
 * Each button holds a cooldown after it fires, mirroring the server's
 * per-minute budget, so a repeated click cannot run into the rate limit.
 */
export function useLeadScoutJobs() {
    const queryCache = useQueryCache();

    const ingestCooldown = ref<number>(0);
    const discoverCooldown = ref<number>(0);
    const rescoreCooldown = ref<number>(0);

    const { pause, resume } = useIntervalFn(
        () => {
            for (const cooldown of [
                ingestCooldown,
                discoverCooldown,
                rescoreCooldown,
            ]) {
                if (cooldown.value > 0) {
                    cooldown.value -= 1;
                }
            }

            if (
                ingestCooldown.value <= 0 &&
                discoverCooldown.value <= 0 &&
                rescoreCooldown.value <= 0
            ) {
                ingestCooldown.value = 0;
                discoverCooldown.value = 0;
                rescoreCooldown.value = 0;
                pause();
            }
        },
        1000,
        { immediate: false },
    );

    function arm(cooldown: Ref<number>, seconds: number): void {
        cooldown.value = seconds;
        resume();
    }

    /**
     * A 429 already carries the server's `Retry-After`; honor it instead of
     * the mirror budget so the button unlocks exactly when the limit resets.
     */
    function armFromError(
        cooldown: Ref<number>,
        fallback: number,
        error: unknown,
    ): void {
        const retryAfter = error instanceof HttpError ? error.retryAfter : null;
        arm(cooldown, retryAfter ?? fallback);
    }

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
            arm(ingestCooldown, INGEST_COOLDOWN_SECONDS);
        },
        onError(error: unknown) {
            if (error instanceof HttpError && error.status === 429) {
                armFromError(ingestCooldown, INGEST_COOLDOWN_SECONDS, error);
            }

            toast.error(errorMessage(error, 'Failed to queue the ingest run.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: SOURCES_KEY }),
    });

    const runDiscover = useMutation({
        mutation: () =>
            httpJson<QueuedResponse>(toUrl(discover()), { method: 'POST' }),
        onSuccess() {
            toast.success('Agency discovery queued.');
            arm(discoverCooldown, DISCOVER_COOLDOWN_SECONDS);
        },
        onError(error: unknown) {
            if (error instanceof HttpError && error.status === 429) {
                armFromError(
                    discoverCooldown,
                    DISCOVER_COOLDOWN_SECONDS,
                    error,
                );
            }

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
            arm(rescoreCooldown, RESCORE_COOLDOWN_SECONDS);
        },
        onError(error: unknown) {
            if (error instanceof HttpError && error.status === 429) {
                armFromError(rescoreCooldown, RESCORE_COOLDOWN_SECONDS, error);
            }

            toast.error(errorMessage(error, 'Failed to queue the re-score.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    return {
        runSource,
        runIngest,
        runDiscover,
        runRescore,
        ingestCooldown,
        discoverCooldown,
        rescoreCooldown,
    };
}

type StatusResponse = {
    queues: {
        'lead-scout': number;
        'lead-scout-llm': number;
        default: number;
    };
};

/** Refresh while the jobs dialog is open — comfortably inside its 60/min. */
const STATUS_POLL_INTERVAL_MS = 5000;

/**
 * Queue depth behind the "Run jobs" dialog, so the operator watches the
 * backlog drain instead of a silent 202. The timer is owned by the watcher:
 * `onWatcherCleanup` tears it down when the dialog closes and on scope
 * disposal, so it cannot outlive the page.
 */
export function useLeadScoutStatus(enabled: MaybeRefOrGetter<boolean>) {
    const pendingJobs = ref<number>(0);
    const pendingExtractions = ref<number>(0);

    watch(
        () => toValue(enabled),
        (open) => {
            if (!open) {
                return;
            }

            const load = (): void => {
                void httpJson<StatusResponse>(statusRoute().url)
                    .then((response) => {
                        pendingJobs.value = response.queues['lead-scout'];
                        pendingExtractions.value =
                            response.queues['lead-scout-llm'];
                    })
                    // A failed poll is not news — the next tick retries, and
                    // the job buttons surface their own errors.
                    .catch(() => undefined);
            };

            load();

            const timer = window.setInterval(load, STATUS_POLL_INTERVAL_MS);

            onWatcherCleanup(() => window.clearInterval(timer));
        },
        { immediate: true },
    );

    return { pendingJobs, pendingExtractions };
}
