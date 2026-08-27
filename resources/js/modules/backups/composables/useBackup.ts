import { useQuery } from '@pinia/colada';
import type { MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';
import { httpJson } from '@/lib/http';
import { show } from '@/routes/backups/admin';
import type { Backup } from '../types';

/**
 * One backup archive, for the detail dialog on the index page.
 *
 * The backend exposes `show` only as a JSON endpoint
 * (`GET /data/admin/backups/{uuid}`), not an Inertia page — there is no
 * `Show.vue`. The dialog fetches on demand and the query stays disabled until a
 * row is picked, so opening the page costs nothing extra.
 */
export function useBackup(uuid: MaybeRefOrGetter<string | null>) {
    const currentUuid = computed<string | null>(() => toValue(uuid));

    const { data, ...query } = useQuery<Backup>({
        key: () => ['backups', 'detail', currentUuid.value],
        query: () => {
            const id = currentUuid.value;

            if (id === null) {
                return Promise.reject(new Error('No backup selected.'));
            }

            return httpJson<Backup>(show.url({ uuid: id }));
        },
        enabled: () => currentUuid.value !== null,
        staleTime: 1000 * 60,
        gcTime: 1000 * 60 * 5,
    });

    return { ...query, backup: data };
}
