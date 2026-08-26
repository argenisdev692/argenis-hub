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
} from '@/routes/services/admin';
import type { Service, ServiceWritePayload } from '../types';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const SERVICES_KEY = ['services'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useServiceMutations() {
    const queryCache = useQueryCache();

    const createService = useMutation({
        mutation: (payload: ServiceWritePayload) =>
            httpJson<Service>(toUrl(store()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Service created.');
            queryCache.invalidateQueries({ key: SERVICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the service.'));
        },
    });

    const updateService = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: ServiceWritePayload;
        }) =>
            httpJson<Service>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            toast.success('Service updated.');
            queryCache.invalidateQueries({ key: SERVICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the service.'));
        },
    });

    const deleteService = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Service deleted.');
            queryCache.invalidateQueries({ key: SERVICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the service.'));
        },
    });

    const restoreService = useMutation({
        mutation: (uuid: string) =>
            httpJson<Service>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Service restored.');
            queryCache.invalidateQueries({ key: SERVICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the service.'));
        },
    });

    const bulkDeleteServices = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'service' : 'services'} deleted.`,
            );
            queryCache.invalidateQueries({ key: SERVICES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected services.'),
            );
        },
    });

    const bulkRestoreServices = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'service' : 'services'} restored.`,
            );
            queryCache.invalidateQueries({ key: SERVICES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected services.'),
            );
        },
    });

    return {
        createService,
        updateService,
        deleteService,
        restoreService,
        bulkDeleteServices,
        bulkRestoreServices,
    };
}
