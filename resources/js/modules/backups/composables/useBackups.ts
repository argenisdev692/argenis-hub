import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/backups/admin';
import { buildBackupQueryParams } from '../helpers/buildBackupQueryParams';
import { defaultBackupFilters } from '../schemas/backupFilterSchema';
import type { BackupFilters, BackupPage } from '../types';

export { defaultBackupFilters };

/**
 * The backups list, in the one shape the admin page needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so a
 * private `filters` ref cannot de-synchronise from anything — see the
 * `defineQuery` decision rule in `FRONTEND/SKILL.md` §6.
 *
 * `AdminBackupController::index()` answers `Accept: application/json` with
 * Laravel's flat paginator, so this reads it straight over `httpJson` rather
 * than through an Inertia visit (the page renders no `backups` prop, as in the
 * Services and Activity Log modules).
 */
export function useBackups() {
    const filters = ref<BackupFilters>(defaultBackupFilters());

    /**
     * Filter half shared with the export menu — see
     * `buildBackupQueryParams` — plus pagination, which is query-only and
     * must never reach an export URL.
     */
    const queryParams = computed(() => ({
        ...buildBackupQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<BackupPage>({
        key: () => ['backups', { ...queryParams.value }],
        query: () =>
            httpJson<BackupPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const backups = computed(() => data.value?.data ?? []);

    const meta = computed<PaginationMeta | undefined>(() =>
        data.value
            ? {
                  current_page: data.value.current_page,
                  last_page: data.value.last_page,
                  per_page: data.value.per_page,
                  from: data.value.from,
                  to: data.value.to,
                  total: data.value.total,
              }
            : undefined,
    );

    return { ...query, data, backups, meta, filters };
}
