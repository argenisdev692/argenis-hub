import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { index, paste } from '@/routes/cv-studio/references';
import type { StudioReference } from '../types';

export const STUDIO_REFERENCES_KEY = ['studio-references'];

export type ReferencePage = {
    data: StudioReference[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export function useReferences() {
    const page = ref(1);

    const { data, ...query } = useQuery<ReferencePage>({
        key: () => [...STUDIO_REFERENCES_KEY, page.value],
        query: () =>
            httpJson<ReferencePage>(toUrl(index({ query: { page: page.value } }))),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    return {
        ...query,
        references: computed(() => data.value?.data ?? []),
        total: computed(() => data.value?.total ?? 0),
        pageCount: computed(() => data.value?.last_page ?? 1),
        page,
    };
}

export function usePasteJobText() {
    const queryCache = useQueryCache();

    const pasteText = useMutation({
        mutation: ({ uuid, text }: { uuid: string; text: string }) =>
            httpJson<unknown>(toUrl(paste(uuid)), {
                method: 'POST',
                body: { text },
            }),
        onSuccess() {
            toast.success('Posting text saved — scored as supplied by you.');
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to save the pasted text.',
            );
        },
        onSettled: async () => {
            await queryCache.invalidateQueries({ key: STUDIO_REFERENCES_KEY });
            await queryCache.invalidateQueries({
                key: ['studio-postings'],
            });
        },
    });

    return { pasteText };
}
