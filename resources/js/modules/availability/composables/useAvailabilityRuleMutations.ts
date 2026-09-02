import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
} from '@/routes/availability-rules';
import { AVAILABILITY_RULES_KEY } from './useAvailabilityRules';

/**
 * The row and bulk actions the weekly-rules list offers. Create and update are
 * absent on purpose: they live in `useAvailabilityRuleForm`, which submits
 * through Inertia to get the redirect-and-flash round trip that maps a 422 back
 * onto the offending field — and the overlap rule on `start_time` is exactly the
 * kind of error that has to land under the input rather than in a toast.
 *
 * ## On the responses these endpoints return
 *
 * These four routes answer with `back()` — they were written for an Inertia
 * form. Sent with `Accept: application/json`, the 302 is followed by `fetch` (as
 * a GET, per the fetch spec) back to `GET /availability-rules`, which serves its
 * JSON branch. So the happy path resolves with a list payload nobody reads, and
 * a 422 or 403 still arrives as JSON with a usable message — which is the part
 * that matters. Same reasoning as `useBlogCategoryMutations`.
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

export function useAvailabilityRuleMutations() {
    const queryCache = useQueryCache();

    const deleteRule = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Availability rule suspended.');
            queryCache.invalidateQueries({ key: AVAILABILITY_RULES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the availability rule.'),
            );
        },
    });

    /**
     * Restoring can legitimately fail with a 422: while the row was suspended,
     * another available slot may have grown over the same window, and the
     * overlap invariant is enforced on the way back in. The server's own message
     * is surfaced rather than a generic one for exactly that case.
     */
    const restoreRule = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Availability rule restored.');
            queryCache.invalidateQueries({ key: AVAILABILITY_RULES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the availability rule.'),
            );
        },
    });

    /**
     * The count comes from the request, not the response: `bulkDelete()` flashes
     * its total into a redirect this client discards. The two only disagree if a
     * row was already trashed by someone else mid-selection, and the list
     * refresh that follows tells that story better than the toast could.
     */
    const bulkDeleteRules = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'rule', 'rules')} suspended.`);
            queryCache.invalidateQueries({ key: AVAILABILITY_RULES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the selected rules.'),
            );
        },
    });

    const bulkRestoreRules = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'rule', 'rules')} restored.`);
            queryCache.invalidateQueries({ key: AVAILABILITY_RULES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected rules.'),
            );
        },
    });

    return { deleteRule, restoreRule, bulkDeleteRules, bulkRestoreRules };
}
