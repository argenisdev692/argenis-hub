import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
    store,
    update,
} from '@/routes/roles';
import { InertiaWriteError, inertiaWrite } from '../helpers/inertiaWrite';
import type { RoleWritePayload } from '../types';
import { PERMISSIONS_KEY } from './usePermissionCatalog';
import { ROLES_KEY } from './useRoles';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof InertiaWriteError ? error.message : fallback;
}

/**
 * Writes against the role endpoints.
 *
 * Every one of them answers with an Inertia redirect rather than JSON, so the
 * transport is {@see inertiaWrite} instead of `httpJson` — see that file for
 * why. The Pinia Colada wrapper is what the rest of the app expects: a
 * `mutateAsync` the confirm modals can await, an `isLoading` the toolbar can
 * disable on, and one invalidation point per write.
 *
 * Bulk delete and bulk restore ship as a pair, per `FRONTEND/SKILL.md` §8.
 * Neither is optimistic: `SUPER_ADMIN` and friends are refused by
 * `ProtectedRoleException` on the server, so removing a row on the way out
 * would show a deletion that never happened. The list is invalidated instead
 * and the table shows what the server actually did.
 */
export function useRoleMutations() {
    const queryCache = useQueryCache();

    function invalidateRoles(): void {
        queryCache.invalidateQueries({ key: ROLES_KEY });
    }

    const createRole = useMutation({
        mutation: (payload: RoleWritePayload) => inertiaWrite(store(), payload),
        onSuccess() {
            toast.success('Role created.');
            invalidateRoles();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the role.'));
        },
    });

    const updateRole = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: RoleWritePayload;
        }) => inertiaWrite(update(uuid), payload),
        onSuccess() {
            toast.success('Role updated.');
            invalidateRoles();
            // A role's grants changed, so every permission's `roles_count` may have.
            queryCache.invalidateQueries({ key: PERMISSIONS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the role.'));
        },
    });

    const deleteRole = useMutation({
        mutation: (uuid: string) => inertiaWrite(destroy(uuid)),
        onSuccess() {
            toast.success('Role suspended.');
            invalidateRoles();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suspend the role.'));
        },
    });

    const restoreRole = useMutation({
        mutation: (uuid: string) => inertiaWrite(restore(uuid)),
        onSuccess() {
            toast.success('Role restored.');
            invalidateRoles();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the role.'));
        },
    });

    const bulkDeleteRoles = useMutation({
        mutation: (uuids: string[]) => inertiaWrite(bulkDelete(), { uuids }),
        onSuccess(_result, uuids) {
            toast.success(
                `${uuids.length} ${uuids.length === 1 ? 'role' : 'roles'} suspended.`,
            );
            invalidateRoles();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the selected roles.'),
            );
        },
    });

    const bulkRestoreRoles = useMutation({
        mutation: (uuids: string[]) => inertiaWrite(bulkRestore(), { uuids }),
        onSuccess(_result, uuids) {
            toast.success(
                `${uuids.length} ${uuids.length === 1 ? 'role' : 'roles'} restored.`,
            );
            invalidateRoles();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected roles.'),
            );
        },
    });

    return {
        createRole,
        updateRole,
        deleteRole,
        restoreRole,
        bulkDeleteRoles,
        bulkRestoreRoles,
    };
}
