import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import type { PaginatedPage, PaginationMeta } from '@/common/table';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { index, paste } from '@/routes/cv-studio/references';
import type { StudioReference } from '../types';
import { STUDIO_POSTINGS_KEY } from './usePostings';

export const STUDIO_REFERENCES_KEY = ['studio-references'];

/** Link-only postings waiting for the user to paste the text they read. */
export function useReferences() {
    const page = ref(1);

    const { data, ...query } = useQuery<PaginatedPage<StudioReference>>({
        key: () => [...STUDIO_REFERENCES_KEY, page.value],
        query: () =>
            httpJson<PaginatedPage<StudioReference>>(
                toUrl(index({ query: { page: page.value } })),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const meta = computed<PaginationMeta>(() => ({
        current_page: data.value?.current_page ?? 1,
        last_page: data.value?.last_page ?? 1,
        per_page: data.value?.per_page ?? 15,
        from: data.value?.from ?? null,
        to: data.value?.to ?? null,
        total: data.value?.total ?? 0,
    }));

    return {
        ...query,
        references: computed(() => data.value?.data ?? []),
        meta,
        page,
    };
}

/**
 * Saves pasted posting text. A 422 is NOT toasted — the paste form projects
 * it onto the textarea instead (`applyServerErrors`), where it belongs.
 */
export function usePasteJobText() {
    const queryCache = useQueryCache();

    return useMutation({
        mutation: ({ uuid, text }: { uuid: string; text: string }) =>
            httpJson<unknown>(toUrl(paste(uuid)), {
                method: 'POST',
                body: { text },
            }),
        onSuccess() {
            toast.success('Posting text saved — scored as supplied by you.');
        },
        onError(error: unknown) {
            if (error instanceof HttpError && error.status === 422) {
                return;
            }

            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to save the pasted text.',
            );
        },
        onSettled: async () => {
            await Promise.all([
                queryCache.invalidateQueries({ key: STUDIO_REFERENCES_KEY }),
                queryCache.invalidateQueries({ key: STUDIO_POSTINGS_KEY }),
            ]);
        },
    });
}
