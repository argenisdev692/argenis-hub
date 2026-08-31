import { useMutation } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    generateContent,
    generateReel,
    generateSocialCopy,
    suggestTopics,
} from '@/routes/posts/ai';
import type {
    AiEnvelope,
    GeneratedPostContent,
    GenerateContentPayload,
    GenerateVariantPayload,
    PostReelPackage,
    PostSocialCopy,
    PostTopicIdea,
    SuggestTopicsPayload,
} from '../types';

/**
 * The four AI-assist calls behind the Create/Edit panel.
 *
 * Mutations rather than queries even though nothing is written: each call is a
 * real, billed provider request that the user triggers deliberately, and the
 * one behaviour a query would add — refetching on its own — is precisely the
 * behaviour that would make it expensive. Nothing is cached for the same
 * reason: two runs of the same prompt are meant to differ.
 *
 * All four are gated by `permission:CREATE_POSTS` and a tight throttle
 * (10/min, 5/min for the reel package) server-side, which is why 429 gets a
 * message of its own — "slow down" and "it broke" call for different reactions.
 */

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

export function usePostAi() {
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
            postAi<GeneratedPostContent, GenerateContentPayload>(
                { url: toUrl(generateContent()) },
                payload,
            ),
        onSuccess(draft: GeneratedPostContent) {
            // The generator retries until every score passes, and gives up
            // rather than failing outright — so a warning here means "usable,
            // but read it before publishing", not "the call failed".
            if (draft.quality_warning) {
                toast.warning(
                    draft.quality_warning_message ??
                        'The draft is below the quality bar — review it before publishing.',
                );

                return;
            }

            toast.success('Draft generated.');
        },
        onError(error: unknown) {
            toast.error(aiErrorMessage(error, 'Could not generate the draft.'));
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

    return {
        suggestPostTopics,
        generatePostContent,
        generatePostSocialCopy,
        generatePostReel,
    };
}
