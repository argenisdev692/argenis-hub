import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
    store,
    update,
} from '@/routes/permissions';
import { InertiaWriteError, inertiaWrite } from '../helpers/inertiaWrite';
import type { PermissionWritePayload } from '../types';
import { PERMISSIONS_KEY } from './usePermissionCatalog';
import { ROLES_KEY } from './useRoles';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof InertiaWriteError ? error.message : fallback;
}

/**
 * Writes against the permission endpoints — same transport rationale as
 * {@see useRoleMutations}.
 *
 * Each write invalidates the role list too: a suspended permission disappears
 * from every role that held it, so leaving the roles table on a cached page
 * would show grants the server no longer honours.
 */
export function usePermissionMutations() {
    const queryCache = useQueryCache();

    function invalidateBoth(): void {
        queryCache.invalidateQueries({ key: PERMISSIONS_KEY });
        queryCache.invalidateQueries({ key: ROLES_KEY });
    }

    const createPermission = useMutation({
        mutation: (payload: PermissionWritePayload) =>
            inertiaWrite(store(), payload),
        onSuccess() {
            toast.success('Permission created.');
            invalidateBoth();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to create the permission.'),
            );
        },
    });

    const updatePermission = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: PermissionWritePayload;
        }) => inertiaWrite(update(uuid), payload),
        onSuccess() {
            toast.success('Permission updated.');
            invalidateBoth();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to update the permission.'),
            );
        },
    });

    const deletePermission = useMutation({
        mutation: (uuid: string) => inertiaWrite(destroy(uuid)),
        onSuccess() {
            toast.success('Permission suspended.');
            invalidateBoth();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the permission.'),
            );
        },
    });

    const restorePermission = useMutation({
        mutation: (uuid: string) => inertiaWrite(restore(uuid)),
        onSuccess() {
            toast.success('Permission restored.');
            invalidateBoth();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the permission.'),
            );
        },
    });

    const bulkDeletePermissions = useMutation({
        mutation: (uuids: string[]) => inertiaWrite(bulkDelete(), { uuids }),
        onSuccess(_result, uuids) {
            toast.success(
                `${uuids.length} ${uuids.length === 1 ? 'permission' : 'permissions'} suspended.`,
            );
            invalidateBoth();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to suspend the selected permissions.',
                ),
            );
        },
    });

    const bulkRestorePermissions = useMutation({
        mutation: (uuids: string[]) => inertiaWrite(bulkRestore(), { uuids }),
        onSuccess(_result, uuids) {
            toast.success(
                `${uuids.length} ${uuids.length === 1 ? 'permission' : 'permissions'} restored.`,
            );
            invalidateBoth();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to restore the selected permissions.',
                ),
            );
        },
    });

    return {
        createPermission,
        updatePermission,
        deletePermission,
        restorePermission,
        bulkDeletePermissions,
        bulkRestorePermissions,
    };
}
