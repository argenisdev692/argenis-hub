import { useMutation } from '@pinia/colada';
import { onWatcherCleanup, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    generateContent,
    generateReel,
    generateSocialCopy,
    generationStatus,
    suggestTopics,
} from '@/routes/posts/ai';
import type {
    AiEnvelope,
    GeneratedPostContent,
    GenerateContentPayload,
    GenerateVariantPayload,
    PostAiGeneration,
    PostReelPackage,
    PostSocialCopy,
    PostTopicIdea,
    SuggestTopicsPayload,
} from '../types';

/**
 * The AI-assist calls behind the Create/Edit panel.
 *
 * Topics, social copy and the reel package are single provider calls, answered
 * inline. The blog draft is not: `generate-content` answers `202` with a
 * `queued` row and hands an up-to-5-iteration quality loop to
 * `GeneratePostContentJob`, so this composable starts a poll rather than
 * awaiting a response that would sit open for minutes.
 *
 * Mutations rather than queries throughout, even though only the draft writes
 * anything: each call is a real, billed provider request the user triggers
 * deliberately, and the one behaviour a query would add — refetching on its
 * own — is precisely the behaviour that would make it expensive.
 *
 * ## Why polling and not the broadcast channel
 *
 * The backend does emit `PostAiGenerationProgress` on a private channel, which
 * is strictly better. But no Echo client is wired into this app —
 * `@laravel/multiplex` sits in `optionalDependencies` and `app.ts` registers no
 * broadcaster — so subscribing here would mean adding a dependency and a
 * broadcast connection for one panel. `GET /posts/ai/generations/{uuid}` exists
 * precisely as the fallback, and its `throttle:post-ai-status` (30/min) sets
 * the ceiling this interval is chosen to sit under. Switch to the channel when
 * Echo lands; the shape of `onReady` will not change.
 *
 * All calls are gated by `permission:CREATE_POSTS` and a tight named throttle
 * server-side, which is why 429 gets a message of its own — "slow down" and
 * "it broke" call for different reactions.
 */

/** Comfortably inside the status endpoint's 30/min even on a long run. */
const POLL_INTERVAL_MS = 5000;

/**
 * A ceiling on the poll, so a job that dies without ever reaching a terminal
 * state stops a timer that would otherwise outlive the page. Five iterations of
 * the quality loop is the documented worst case; 10 minutes is generous against
 * it, and the job's own 900s timeout will have written `failed` well before.
 */
const POLL_TIMEOUT_MS = 10 * 60 * 1000;

function aiErrorMessage(error: unknown, fallback: string): string {
    if (!(error instanceof HttpError)) {
        return fallback;
    }

    if (error.status === 429) {
        return 'Too many AI requests — wait a moment and try again.';
    }

    return error.message;
}

/**
 * Every AI endpoint answers `{ data: … }`; unwrapping here keeps the envelope
 * out of the components, which only ever want what is inside it.
 */
function postAi<TResult, TPayload extends object>(
    target: { url: string },
    payload: TPayload,
): Promise<TResult> {
    return httpJson<AiEnvelope<TResult>>(target.url, {
        method: 'POST',
        body: payload,
    }).then((envelope) => envelope.data);
}

export type UsePostAiOptions = {
    /**
     * Called once a draft generation settles successfully. The finished draft
     * is handed over whole so the caller can apply it to the form.
     */
    onReady?: (draft: GeneratedPostContent) => void;
};

export function usePostAi({ onReady }: UsePostAiOptions = {}) {
    /** Set once a generation is accepted; drives the poll. Null when idle. */
    const generatingUuid = ref<string | null>(null);

    /** The last polled snapshot, so the panel can show the live phase. */
    const generation = ref<PostAiGeneration | null>(null);

    const suggestPostTopics = useMutation({
        mutation: (payload: SuggestTopicsPayload) =>
            postAi<PostTopicIdea[], SuggestTopicsPayload>(
                { url: toUrl(suggestTopics()) },
                payload,
            ),
        onError(error: unknown) {
            toast.error(aiErrorMessage(error, 'Could not suggest topics.'));
        },
    });

    const generatePostContent = useMutation({
        mutation: (payload: GenerateContentPayload) =>
            postAi<PostAiGeneration, GenerateContentPayload>(
                { url: toUrl(generateContent()) },
                payload,
            ),
        onSuccess(accepted: PostAiGeneration) {
            generation.value = accepted;
            generatingUuid.value = accepted.uuid;
            toast.info('Generating — this usually takes a couple of minutes.');
        },
        onError(error: unknown) {
            toast.error(aiErrorMessage(error, 'Could not start the draft.'));
        },
    });

    const generatePostSocialCopy = useMutation({
        mutation: (payload: GenerateVariantPayload) =>
            postAi<PostSocialCopy, GenerateVariantPayload>(
                { url: toUrl(generateSocialCopy()) },
                payload,
            ),
        onSuccess() {
            toast.success('Social copy generated.');
        },
        onError(error: unknown) {
            toast.error(
                aiErrorMessage(error, 'Could not generate the social copy.'),
            );
        },
    });

    const generatePostReel = useMutation({
        mutation: (payload: GenerateVariantPayload) =>
            postAi<PostReelPackage, GenerateVariantPayload>(
                { url: toUrl(generateReel()) },
                payload,
            ),
        onSuccess() {
            toast.success('Reel package generated.');
        },
        onError(error: unknown) {
            toast.error(
                aiErrorMessage(error, 'Could not generate the reel package.'),
            );
        },
    });

    /**
     * Settles one finished run: hand the draft to the caller, or explain why
     * there isn't one.
     *
     * A `quality_warning` is NOT a failure — the loop retries until every score
     * passes and then gives up rather than failing outright, so it means
     * "usable, but read it before publishing".
     */
    function settle(finished: PostAiGeneration): void {
        if (finished.status === 'failed' || finished.result === null) {
            toast.error(
                finished.error_message ?? 'The draft could not be generated.',
            );

            return;
        }

        if (finished.result.quality_warning) {
            toast.warning(
                finished.result.quality_warning_message ??
                    'The draft is below the quality bar — review it before publishing.',
            );
        } else {
            toast.success('Draft generated.');
        }

        onReady?.(finished.result);
    }

    /**
     * Polls the accepted run until it reports `is_terminal`.
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
                    'Generation is taking longer than expected. Try again in a moment.',
                );

                return;
            }

            void httpJson<AiEnvelope<PostAiGeneration>>(
                toUrl(generationStatus(uuid)),
            )
                .then((envelope) => {
                    generation.value = envelope.data;

                    if (!envelope.data.is_terminal) {
                        return;
                    }

                    generatingUuid.value = null;
                    settle(envelope.data);
                })
                // A single failed poll is not a failed generation — the request
                // may simply have out-run a rate limit. Stop only on the
                // timeout above.
                .catch(() => undefined);
        }, POLL_INTERVAL_MS);

        onWatcherCleanup(() => window.clearInterval(timer));
    });

    return {
        generatingUuid,
        generation,
        suggestPostTopics,
        generatePostContent,
        generatePostSocialCopy,
        generatePostReel,
    };
}
