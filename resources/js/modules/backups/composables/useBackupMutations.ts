import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { bulkDelete, destroy, store } from '@/routes/backups/admin';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const BACKUPS_KEY = ['backups'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useBackupMutations() {
    const queryCache = useQueryCache();

    /**
     * Queues an on-demand database backup. The archive is produced off the
     * request cycle (`202 Accepted`) and shows up in the list once the job
     * runs, so the invalidation here only refreshes what already exists — the
     * user still needs a beat before the new row appears.
     */
    const runBackup = useMutation({
        mutation: () =>
            httpJson<{ status: string }>(toUrl(store()), { method: 'POST' }),
        onSuccess() {
            toast.success(
                'Backup queued. It will appear in the list once the job finishes.',
            );
            queryCache.invalidateQueries({ key: BACKUPS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to queue the backup.'));
        },
    });

    const deleteBackup = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Backup deleted.');
            queryCache.invalidateQueries({ key: BACKUPS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the backup.'));
        },
    });

    const bulkDeleteBackups = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'backup' : 'backups'} deleted.`,
            );
            queryCache.invalidateQueries({ key: BACKUPS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected backups.'),
            );
        },
    });

    return { runBackup, deleteBackup, bulkDeleteBackups };
}
