import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { STUDIO_VERSIONS_KEY } from './useCvVersions';

export type TailorLanguage = 'en' | 'es' | 'pt-PT';

type TailorResponse = {
    data: { uuid: string };
};

export type TailorChatInput = {
    postingUuid: string;
    structureUuid: string;
    language: TailorLanguage;
    notes?: string;
};

/**
 * Agent-chat tailoring for one posting: the panel sends a confirmed
 * structure UUID plus language and operator notes — CV content always
 * resolves server-side, never from the client. Notes are advisory: the
 * agent must still ground every skill in the source CV.
 */
export function useTailorChat() {
    const queryCache = useQueryCache();

    const tailor = useMutation({
        mutation: (input: TailorChatInput) =>
            httpJson<TailorResponse>(
                `/cv-studio/postings/${input.postingUuid}/tailor`,
                {
                    method: 'POST',
                    body: {
                        structure_uuid: input.structureUuid,
                        language: input.language,
                        ...(input.notes ? { notes: input.notes } : {}),
                    },
                },
            ),
        onSuccess() {
            toast.success(
                'Tailored version created — find it on the Versions page.',
            );
            queryCache.invalidateQueries({ key: STUDIO_VERSIONS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to tailor the CV.',
            );
        },
    });

    return { tailor };
}
