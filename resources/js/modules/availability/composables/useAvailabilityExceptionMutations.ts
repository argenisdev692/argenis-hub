import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
} from '@/routes/availability-exceptions';
import { AVAILABILITY_EXCEPTIONS_KEY } from './useAvailabilityExceptions';

/**
 * The row and bulk actions the date-exceptions list offers. Create and update
 * live in `useAvailabilityExceptionForm`, which submits through Inertia so a 422
 * lands under the offending field — and the "one active exception per date"
 * unique rule is precisely the error that has to appear beside the date input
 * rather than in a toast.
 *
 * ## On the responses these endpoints return
 *
 * These four routes answer with `back()`. Sent with `Accept: application/json`,
 * the 302 is followed by `fetch` (as a GET, per the fetch spec) back to
 * `GET /availability-exceptions`, which serves its JSON branch. So the happy
 * path resolves with a list payload nobody reads, and a 422 or 403 still arrives
 * as JSON with a usable message — which is the part that matters.
 */

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function useAvailabilityExceptionMutations() {
    const queryCache = useQueryCache();

    const deleteException = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Date exception suspended.');
            queryCache.invalidateQueries({ key: AVAILABILITY_EXCEPTIONS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the date exception.'),
            );
        },
    });

    /**
     * Restoring can legitimately fail with a 422: the unique rule ignores
     * soft-deleted rows, so another exception may have been created for the same
     * date while this one was suspended. The server's own message is surfaced
     * rather than a generic one for exactly that case.
     */
    const restoreException = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Date exception restored.');
            queryCache.invalidateQueries({ key: AVAILABILITY_EXCEPTIONS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the date exception.'),
            );
        },
    });

    /**
     * The count comes from the request, not the response: `bulkDelete()` flashes
     * its total into a redirect this client discards.
     */
    const bulkDeleteExceptions = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(
                `${pluralize(count, 'exception', 'exceptions')} suspended.`,
            );
            queryCache.invalidateQueries({ key: AVAILABILITY_EXCEPTIONS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to suspend the selected exceptions.',
                ),
            );
        },
    });

    const bulkRestoreExceptions = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(
                `${pluralize(count, 'exception', 'exceptions')} restored.`,
            );
            queryCache.invalidateQueries({ key: AVAILABILITY_EXCEPTIONS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to restore the selected exceptions.',
                ),
            );
        },
    });

    return {
        deleteException,
        restoreException,
        bulkDeleteExceptions,
        bulkRestoreExceptions,
    };
}
