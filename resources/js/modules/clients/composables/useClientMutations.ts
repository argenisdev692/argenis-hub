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
} from '@/routes/clients/admin';
import type { Client, ClientWritePayload } from '../types';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const CLIENTS_KEY = ['clients'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useClientMutations() {
    const queryCache = useQueryCache();

    const createClient = useMutation({
        mutation: (payload: ClientWritePayload) =>
            httpJson<Client>(toUrl(store()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Client created.');
            queryCache.invalidateQueries({ key: CLIENTS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the client.'));
        },
    });

    const updateClient = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: ClientWritePayload;
        }) =>
            httpJson<Client>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            toast.success('Client updated.');
            queryCache.invalidateQueries({ key: CLIENTS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the client.'));
        },
    });

    const deleteClient = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Client deleted.');
            queryCache.invalidateQueries({ key: CLIENTS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the client.'));
        },
    });

    const restoreClient = useMutation({
        mutation: (uuid: string) =>
            httpJson<Client>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Client restored.');
            queryCache.invalidateQueries({ key: CLIENTS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the client.'));
        },
    });

    const bulkDeleteClients = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'client' : 'clients'} deleted.`,
            );
            queryCache.invalidateQueries({ key: CLIENTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected clients.'),
            );
        },
    });

    const bulkRestoreClients = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'client' : 'clients'} restored.`,
            );
            queryCache.invalidateQueries({ key: CLIENTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected clients.'),
            );
        },
    });

    return {
        createClient,
        updateClient,
        deleteClient,
        restoreClient,
        bulkDeleteClients,
        bulkRestoreClients,
    };
}
