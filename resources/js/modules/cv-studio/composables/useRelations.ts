import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { confirm, index, reject } from '@/routes/cv-studio/relations';
import type { StudioRelation } from '../types';

export const STUDIO_RELATIONS_KEY = ['studio-relations'];

export function usePendingRelations() {
    const { data, ...query } = useQuery<{ data: StudioRelation[] }>({
        key: () => [...STUDIO_RELATIONS_KEY],
        query: () =>
            httpJson<{ data: StudioRelation[] }>(toUrl(index())),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
    });

    return { ...query, relations: computed(() => data.value?.data ?? []) };
}

export function useRelationMutations() {
    const queryCache = useQueryCache();

    const invalidate = async () =>
        await queryCache.invalidateQueries({ key: STUDIO_RELATIONS_KEY });

    const confirmRelation = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(confirm(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Relation confirmed — it now grants credit.');
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to confirm the relation.',
            );
        },
        onSettled: invalidate,
    });

    const rejectRelation = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(reject(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Relation rejected.');
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to reject the relation.',
            );
        },
        onSettled: invalidate,
    });

    return { confirmRelation, rejectRelation };
}
