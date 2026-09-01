import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    publish,
    restore,
} from '@/routes/campaigns';

/**
 * The row and bulk actions the list offers. Update is absent on purpose: it is
 * a full page, submitted through Inertia by `useCampaignForm`, because it wants
 * the redirect-and-flash round trip. Create is absent because there is no
 * create endpoint at all — a campaign is always AI-born via `useCampaignAi`.
 *
 * ## On the responses these endpoints return
 *
 * These five routes answer with `back()` — they were written for an Inertia
 * form. Sent with `Accept: application/json`, the 302 is followed by `fetch`
 * (as a GET, per the fetch spec) back to `GET /campaigns`, which serves its
 * JSON branch. So the happy path resolves with a list payload nobody reads, and
 * a 422 still arrives as JSON with a usable `errors` bag — which is the part
 * that matters. Same reasoning as `useSocialMediaContentMutations`.
 *
 * Deliberately not routed through Inertia's `router` instead: the list is Pinia
 * Colada server state, and a `router.visit` would re-render the whole page to
 * refresh a prop this table does not read.
 */

/** Every mutation below touches the same list, so one key invalidates all of it. */
const CAMPAIGNS_KEY = ['campaigns'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function useCampaignMutations() {
    const queryCache = useQueryCache();

    const deleteCampaign = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Campaign suspended.');
            queryCache.invalidateQueries({ key: CAMPAIGNS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suspend the campaign.'));
        },
    });

    const restoreCampaign = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Campaign restored.');
            queryCache.invalidateQueries({ key: CAMPAIGNS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the campaign.'));
        },
    });

    /**
     * Marks a campaign published immediately, bypassing the scheduler.
     *
     * Gated by `PUBLISH_CAMPAIGNS` at the route, and by `PermissionGuard` at
     * the call site — the two are independent on purpose (`OWASP/SKILL.md`: the
     * UI guard is a courtesy, the middleware is the control). It is also its
     * own permission rather than part of `UPDATE_CAMPAIGNS`, because
     * `RolePermissionSeeder` deliberately splits "edit the draft" from "spend
     * the ad budget".
     */
    const publishCampaign = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(publish(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Campaign marked as published.');
            queryCache.invalidateQueries({ key: CAMPAIGNS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to publish the campaign.'));
        },
    });

    /**
     * The count comes from the request, not the response: `bulkDelete()`
     * flashes its total into a redirect this client discards. The two only
     * disagree if a row was already trashed by someone else mid-selection, and
     * the list refresh that follows tells that story better than the toast
     * could.
     */
    const bulkDeleteCampaigns = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(
                `${pluralize(count, 'campaign', 'campaigns')} suspended.`,
            );
            queryCache.invalidateQueries({ key: CAMPAIGNS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to suspend the selected campaigns.',
                ),
            );
        },
    });

    const bulkRestoreCampaigns = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(
                `${pluralize(count, 'campaign', 'campaigns')} restored.`,
            );
            queryCache.invalidateQueries({ key: CAMPAIGNS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to restore the selected campaigns.',
                ),
            );
        },
    });

    return {
        deleteCampaign,
        restoreCampaign,
        publishCampaign,
        bulkDeleteCampaigns,
        bulkRestoreCampaigns,
    };
}
