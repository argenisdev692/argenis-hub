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
} from '@/routes/cv-studio/postings';
import type { StudioScore, StudioScoreInput } from '../types';
import { STUDIO_POSTINGS_KEY } from './usePostings';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function usePostingMutations() {
    const queryCache = useQueryCache();

    const invalidate = async () =>
        await queryCache.invalidateQueries({ key: STUDIO_POSTINGS_KEY });

    const deletePosting = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Posting suspended.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suspend the posting.'));
        },
        onSettled: invalidate,
    });

    const restorePosting = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Posting restored.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the posting.'));
        },
        onSettled: invalidate,
    });

    const bulkDeletePostings = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'Posting', 'Postings')} suspended.`);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the selected postings.'),
            );
        },
        onSettled: invalidate,
    });

    const bulkRestorePostings = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'Posting', 'Postings')} restored.`);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected postings.'),
            );
        },
        onSettled: invalidate,
    });

    const scorePosting = useMutation({
        mutation: ({ uuid, input }: { uuid: string; input: StudioScoreInput }) =>
            httpJson<{ data: StudioScore }>(toUrl(score(uuid)), {
                method: 'POST',
                body: input,
            }),
        onSuccess() {
            toast.success('Posting scored.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to score the posting.'));
        },
        onSettled: invalidate,
    });

    const rescorePosting = useMutation({
        mutation: ({ uuid, input }: { uuid: string; input: StudioScoreInput }) =>
            httpJson<{ data: StudioScore }>(toUrl(rescore(uuid)), {
                method: 'POST',
                body: input,
            }),
        onSuccess() {
            toast.success('Posting rescored from stored rows.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to rescore the posting.'));
        },
        onSettled: invalidate,
    });

    return {
        deletePosting,
        restorePosting,
        bulkDeletePostings,
        bulkRestorePostings,
        scorePosting,
        rescorePosting,
    };
}
