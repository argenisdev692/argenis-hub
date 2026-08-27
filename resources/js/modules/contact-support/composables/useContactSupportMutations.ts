import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
    store,
    update,
} from '@/routes/contact-supports/admin';
import type { ContactSupport, ContactSupportWritePayload } from '../types';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const CONTACT_SUPPORTS_KEY = ['contact-supports'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useContactSupportMutations() {
    const queryCache = useQueryCache();

    const createContactSupport = useMutation({
        mutation: (payload: ContactSupportWritePayload) =>
            httpJson<ContactSupport>(toUrl(store()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Support request created.');
            queryCache.invalidateQueries({ key: CONTACT_SUPPORTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to create the support request.'),
            );
        },
    });

    const updateContactSupport = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: ContactSupportWritePayload;
        }) =>
            httpJson<ContactSupport>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            toast.success('Support request updated.');
            queryCache.invalidateQueries({ key: CONTACT_SUPPORTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to update the support request.'),
            );
        },
    });

    const deleteContactSupport = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Support request deleted.');
            queryCache.invalidateQueries({ key: CONTACT_SUPPORTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the support request.'),
            );
        },
    });

    const restoreContactSupport = useMutation({
        mutation: (uuid: string) =>
            httpJson<ContactSupport>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Support request restored.');
            queryCache.invalidateQueries({ key: CONTACT_SUPPORTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the support request.'),
            );
        },
    });

    const bulkDeleteContactSupports = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'request' : 'requests'} deleted.`,
            );
            queryCache.invalidateQueries({ key: CONTACT_SUPPORTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to delete the selected support requests.',
                ),
            );
        },
    });

    const bulkRestoreContactSupports = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'request' : 'requests'} restored.`,
            );
            queryCache.invalidateQueries({ key: CONTACT_SUPPORTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to restore the selected support requests.',
                ),
            );
        },
    });

    return {
        createContactSupport,
        updateContactSupport,
        deleteContactSupport,
        restoreContactSupport,
        bulkDeleteContactSupports,
        bulkRestoreContactSupports,
    };
}
