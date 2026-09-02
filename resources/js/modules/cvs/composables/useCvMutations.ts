import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { bulkDelete, bulkRestore, destroy, restore } from '@/routes/cvs';
import { CVS_KEY } from './useCvs';

/**
 * The row and bulk actions the list offers. Create and update are absent on
 * purpose: they live in `useCvForm`, which submits through Inertia because they
 * carry a file upload and want the redirect-and-flash round trip that maps a
 * 422 back onto the offending field.
 *
 * ## On the responses these endpoints return
 *
 * All four routes answer with `back()` — they were written for an Inertia form.
 * Sent with `Accept: application/json`, the 302 is followed by `fetch` (as a
 * GET, per the fetch spec) back to `GET /cvs`, which serves its JSON branch. So
 * the happy path resolves with a list payload nobody reads, and a 403 or 422
 * still arrives as JSON with a usable message — which is the part that matters.
 *
 * Deliberately not routed through Inertia's `router` instead: the list is Pinia
 * Colada server state, and a `router.visit` would re-render the whole page to
 * refresh a prop this table does not read.
 */

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function useCvMutations() {
    const queryCache = useQueryCache();

    const deleteCv = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('CV suspended.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suspend the CV.'));
        },
        // `onSettled` rather than `onSuccess`: a failed soft delete can still
        // have moved the row (a concurrent bulk action), and the refetch is
        // what tells the operator which state the list is actually in.
        onSettled: async () =>
            await queryCache.invalidateQueries({ key: CVS_KEY }),
    });

    const restoreCv = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('CV restored.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the CV.'));
        },
        onSettled: async () =>
            await queryCache.invalidateQueries({ key: CVS_KEY }),
    });

    /**
     * The count comes from the request, not the response: `bulkDelete()`
     * flashes its total into a redirect this client discards. The two only
     * disagree if a row was already trashed by someone else mid-selection, and
     * the list refresh that follows tells that story better than the toast
     * could.
     */
    const bulkDeleteCvs = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'CV', 'CVs')} suspended.`);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the selected CVs.'),
            );
        },
        onSettled: async () =>
            await queryCache.invalidateQueries({ key: CVS_KEY }),
    });

    const bulkRestoreCvs = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'CV', 'CVs')} restored.`);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected CVs.'),
            );
        },
        onSettled: async () =>
            await queryCache.invalidateQueries({ key: CVS_KEY }),
    });

    return { deleteCv, restoreCv, bulkDeleteCvs, bulkRestoreCvs };
}
