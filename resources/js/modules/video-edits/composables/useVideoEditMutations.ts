import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    destroy,
    downloadUrl,
    retry,
    review,
    store,
    submit,
} from '@/routes/video-edits/admin';
import { uploadToPresignedUrl } from '../helpers/uploadToPresignedUrl';
import type {
    BulkDeletedVideoEdits,
    CreatedVideoEdit,
    CreateVideoEditPayload,
    DownloadUrl,
    ReviewVideoEditCutsPayload,
    VideoEditDetail,
} from '../types';
import { VIDEO_EDITS_KEY } from './useVideoEdits';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number): string {
    return `${count} ${count === 1 ? 'edit' : 'edits'}`;
}

export type CreateVideoEditInput = {
    payload: CreateVideoEditPayload;
    sources: File[];
    script: File | null;
    /** Reports "uploading 2 of 3" while the files travel to storage. */
    onUploadProgress?: (uploaded: number, total: number) => void;
};

/**
 * Every write the module offers, over the `/data/admin/video-edits` JSON
 * surface. Each settles by invalidating `VIDEO_EDITS_KEY`, which refreshes the
 * table and any open detail page at once.
 */
export function useVideoEditMutations() {
    const queryCache = useQueryCache();

    const invalidate = async (): Promise<void> => {
        await queryCache.invalidateQueries({ key: VIDEO_EDITS_KEY });
    };

    /**
     * The three-step create flow (E2 → AD-1 → E3): declare the draft, PUT each
     * file straight to storage through its presigned URL, then submit. The
     * files never travel through PHP. If an upload fails the draft is simply
     * left behind — it never appears in the history (D17) and the scheduler
     * sweeps expired drafts.
     */
    const createVideoEdit = useMutation({
        mutation: async ({
            payload,
            sources,
            script,
            onUploadProgress,
        }: CreateVideoEditInput): Promise<VideoEditDetail> => {
            const created = await httpJson<CreatedVideoEdit>(toUrl(store()), {
                method: 'POST',
                body: payload,
            });

            const uploads = created.uploads.map((target) => ({
                target,
                file: sources[target.position - 1],
            }));

            if (created.script_upload && script) {
                uploads.push({ target: created.script_upload, file: script });
            }

            let uploaded = 0;
            onUploadProgress?.(uploaded, uploads.length);

            for (const { target, file } of uploads) {
                if (!file) {
                    throw new HttpError(
                        'A file went missing before upload.',
                        0,
                    );
                }

                await uploadToPresignedUrl(target, file);
                onUploadProgress?.(++uploaded, uploads.length);
            }

            return await httpJson<VideoEditDetail>(
                toUrl(submit(created.edit.uuid)),
                { method: 'POST' },
            );
        },
        onSuccess() {
            toast.success('Edit queued. Progress updates live in the list.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to start the edit.'));
        },
        onSettled: invalidate,
    });

    const retryVideoEdit = useMutation({
        mutation: (uuid: string) =>
            httpJson<VideoEditDetail>(toUrl(retry(uuid)), {
                method: 'POST',
                body: {},
            }),
        onSuccess() {
            toast.success('Edit re-queued.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to retry the edit.'));
        },
        onSettled: invalidate,
    });

    /**
     * The owner's answer to the AI cut review. An empty list is a real answer
     * — "keep everything" — and still renders the video.
     */
    const reviewVideoEditCuts = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: ReviewVideoEditCutsPayload;
        }) =>
            httpJson<VideoEditDetail>(toUrl(review(uuid)), {
                method: 'POST',
                body: payload,
            }),
        onSuccess(_edit, { payload }) {
            const count = payload.approved_cut_ids.length;

            toast.success(
                count === 0
                    ? 'Rendering with nothing removed by the AI.'
                    : `Rendering with ${count} ${count === 1 ? 'cut' : 'cuts'} removed.`,
            );
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to save your review.'));
        },
        onSettled: invalidate,
    });

    const deleteVideoEdit = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Edit deleted permanently.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the edit.'));
        },
        onSettled: invalidate,
    });

    /**
     * Processing rows (D14) and rows that vanished mid-selection are skipped by
     * the server rather than failing the batch; the toast says so.
     */
    const bulkDeleteVideoEdits = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<BulkDeletedVideoEdits>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted, skipped }) {
            const message = `${pluralize(deleted)} deleted permanently.`;

            if (skipped > 0) {
                toast.warning(
                    `${message} ${pluralize(skipped)} skipped — still processing or no longer available.`,
                );

                return;
            }

            toast.success(message);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected edits.'),
            );
        },
        onSettled: invalidate,
    });

    /**
     * The signed link is short-lived (D12) and never cached: it is fetched on
     * click and handed straight to the browser's download.
     */
    const downloadVideoEdit = useMutation({
        mutation: (uuid: string) =>
            httpJson<DownloadUrl>(toUrl(downloadUrl(uuid))),
        onSuccess({ url }) {
            window.location.assign(url);
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to prepare the download.'));
        },
    });

    return {
        createVideoEdit,
        retryVideoEdit,
        reviewVideoEditCuts,
        deleteVideoEdit,
        bulkDeleteVideoEdits,
        downloadVideoEdit,
    };
}
