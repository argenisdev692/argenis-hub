import { useMutation } from '@pinia/colada';
import { onWatcherCleanup, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { generateCampaign, status, suggestTopics } from '@/routes/campaigns/ai';
import type {
    AiEnvelope,
    CampaignDetail,
    CampaignTopicIdea,
    GenerateCampaignPayload,
    SuggestCampaignTopicsPayload,
} from '../types';

/**
 * The two-step AI wizard behind `campaigns/Create`.
 *
 * Step 1 (`suggest`) is synchronous — one provider call, answered inline.
 * Step 2 (`generate`) is not: the endpoint answers `202` with a row already
 * persisted in `generating` state and hands the real work to
 * `GenerateCampaignJob`, which runs an up-to-5-iteration quality loop. So this
 * composable starts a poll rather than awaiting a response that would sit open
 * for minutes.
 *
 * ## Why polling and not the broadcast channel
 *
 * The backend does emit `CampaignAiGenerationProgress` on a private channel,
 * which is strictly better. But no Echo client is wired into this app —
 * `@laravel/multiplex` sits in `optionalDependencies` and `app.ts` registers no
 * broadcaster — so subscribing here would mean adding a dependency and a
 * broadcast connection for one screen. `GET /campaigns/ai/{uuid}/status` exists
 * precisely as the fallback, and its `throttle:30,1` sets the ceiling this
 * interval is chosen to sit under. Switch to the channel when Echo lands; the
 * shape of `onReady` will not change.
 */

/** Comfortably inside the endpoint's `throttle:30,1` even on a long run. */
const POLL_INTERVAL_MS = 5000;

/**
 * A ceiling on the poll, so a job that dies without ever leaving `generating`
 * stops a timer that would otherwise outlive the page. 5 iterations of the
 * quality loop is the documented worst case; 10 minutes is generous against it.
 */
const POLL_TIMEOUT_MS = 10 * 60 * 1000;

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export type UseCampaignAiOptions = {
    /**
     * Called once generation settles — successfully or not. The row is handed
     * over whole so the caller can route to the review page, or surface the
     * `quality_warning_message` on a campaign that finished below threshold.
     */
    onReady?: (campaign: CampaignDetail) => void;
};

export function useCampaignAi({ onReady }: UseCampaignAiOptions = {}) {
    /** The ideas from Step 1, kept so Step 2 can pre-fill from a chosen one. */
    const topicIdeas = ref<CampaignTopicIdea[]>([]);

    /** Set once Step 2 is accepted; drives the poll. Null when idle. */
    const generatingUuid = ref<string | null>(null);

    /** The last polled snapshot, so the wizard can show live status. */
    const generatingCampaign = ref<CampaignDetail | null>(null);

    const suggest = useMutation({
        mutation: async (payload: SuggestCampaignTopicsPayload) => {
            const response = await httpJson<AiEnvelope<CampaignTopicIdea[]>>(
                toUrl(suggestTopics()),
                { method: 'POST', body: payload },
            );

            return response.data;
        },
        onSuccess(ideas: CampaignTopicIdea[]) {
            topicIdeas.value = ideas;

            if (ideas.length === 0) {
                toast.info(
                    'The provider returned no angles. Try a narrower niche.',
                );
            }
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suggest angles.'));
        },
    });

    const generate = useMutation({
        mutation: async (payload: GenerateCampaignPayload) => {
            const response = await httpJson<AiEnvelope<CampaignDetail>>(
                toUrl(generateCampaign()),
                { method: 'POST', body: payload },
            );

            return response.data;
        },
        onSuccess(campaign: CampaignDetail) {
            generatingCampaign.value = campaign;
            generatingUuid.value = campaign.uuid;
            toast.success(
                'Generation started. This usually takes a few minutes.',
            );
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to start generation.'));
        },
    });

    /**
     * Polls the accepted campaign until it leaves `generating`.
     *
     * The timer is owned by the watcher, and `onWatcherCleanup` tears it down
     * on every re-run AND on scope disposal — so navigating away mid-generation
     * cannot leave an interval firing against an unmounted page.
     */
    watch(generatingUuid, (uuid) => {
        if (uuid === null) {
            return;
        }

        const startedAt = Date.now();

        const timer = window.setInterval(() => {
            if (Date.now() - startedAt > POLL_TIMEOUT_MS) {
                generatingUuid.value = null;
                toast.error(
                    'Generation is taking longer than expected. Check the list in a moment.',
                );

                return;
            }

            void httpJson<AiEnvelope<CampaignDetail>>(toUrl(status(uuid)))
                .then((response) => {
                    generatingCampaign.value = response.data;

                    if (response.data.status === 'generating') {
                        return;
                    }

                    generatingUuid.value = null;
                    onReady?.(response.data);
                })
                // A single failed poll is not a failed generation — the job may
                // simply have out-run a rate limit. Stop only on the timeout.
                .catch(() => undefined);
        }, POLL_INTERVAL_MS);

        onWatcherCleanup(() => window.clearInterval(timer));
    });

    return {
        topicIdeas,
        generatingUuid,
        generatingCampaign,
        suggest,
        generate,
    };
}
