import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    rescore,
    restore,
    score,
    status,
} from '@/routes/cv-studio/postings';
import { postingStageLabel } from '../helpers/studioPresentation';
import type {
    StudioPostingStage,
    StudioScore,
    StudioScoreInput,
} from '../types';
import { STUDIO_POSTINGS_KEY } from './usePostings';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

/**
 * Every posting write: toast on success, a server message (or fallback) on
 * failure, and a list refetch either way (`onSettled`).
 */
export function usePostingMutations() {
    const queryCache = useQueryCache();

    function feedback<TData>(
        success: (data: TData) => string,
        failure: string,
    ) {
        return {
            onSuccess(data: TData) {
                toast.success(success(data));
            },
            onError(error: unknown) {
                toast.error(errorMessage(error, failure));
            },
            onSettled: async () =>
                await queryCache.invalidateQueries({
                    key: STUDIO_POSTINGS_KEY,
                }),
        };
    }

    const deletePosting = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        ...feedback(
            () => 'Posting suspended.',
            'Failed to suspend the posting.',
        ),
    });

    const restorePosting = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'PATCH' }),
        ...feedback(
            () => 'Posting restored.',
            'Failed to restore the posting.',
        ),
    });

    const bulkDeletePostings = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        ...feedback(
            (count: number) =>
                `${pluralize(count, 'Posting', 'Postings')} suspended.`,
            'Failed to suspend the selected postings.',
        ),
    });

    const bulkRestorePostings = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        ...feedback(
            (count: number) =>
                `${pluralize(count, 'Posting', 'Postings')} restored.`,
            'Failed to restore the selected postings.',
        ),
    });

    /** Moves a posting along the pipeline (save / applied / dismiss …). */
    const updatePostingStage = useMutation({
        mutation: async ({
            uuid,
            stage,
        }: {
            uuid: string;
            stage: StudioPostingStage;
        }) => {
            await httpJson<unknown>(toUrl(status(uuid)), {
                method: 'PUT',
                body: { status: stage },
            });

            return stage;
        },
        ...feedback(
            (stage: StudioPostingStage) =>
                `Moved to “${postingStageLabel(stage)}”.`,
            'Failed to update the posting stage.',
        ),
    });

    const scorePosting = useMutation({
        mutation: ({
            uuid,
            input,
        }: {
            uuid: string;
            input: StudioScoreInput;
        }) =>
            httpJson<{ data: StudioScore }>(toUrl(score(uuid)), {
                method: 'POST',
                body: input,
            }),
        ...feedback(() => 'Posting scored.', 'Failed to score the posting.'),
    });

    const rescorePosting = useMutation({
        mutation: ({
            uuid,
            input,
        }: {
            uuid: string;
            input: StudioScoreInput;
        }) =>
            httpJson<{ data: StudioScore }>(toUrl(rescore(uuid)), {
                method: 'POST',
                body: input,
            }),
        ...feedback(
            () => 'Posting rescored from stored rows.',
            'Failed to rescore the posting.',
        ),
    });

    return {
        deletePosting,
        restorePosting,
        bulkDeletePostings,
        bulkRestorePostings,
        updatePostingStage,
        scorePosting,
        rescorePosting,
    };
}
