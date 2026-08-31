import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { bulkDelete, bulkRestore, destroy, restore } from '@/routes/posts';

/**
 * The row and bulk actions the list offers. Create and update are absent on
 * purpose: those are full pages, submitted through Inertia by `usePostForm`,
 * because they carry a file upload and want the redirect-and-flash round trip.
 *
 * ## On the responses these endpoints return
 *
 * Unlike the JSON CRUD surfaces the other modules talk to, these four routes
 * answer with `back()` — they were written for an Inertia form. Sent with
 * `Accept: application/json`, the 302 is followed by `fetch` (as a GET, per the
 * fetch spec) back to `GET /posts`, which serves its JSON branch. So the happy
 * path resolves with a list payload nobody reads, and a 422 still arrives as
 * JSON with a usable `errors` bag — which is the part that matters.
 *
 * Deliberately not routed through Inertia's `router` instead: the list is
 * Pinia Colada server state, and a `router.visit` would re-render the whole
 * page (re-running the category query with it) to refresh a prop this table
 * does not read.
 */

/** Every mutation below touches the same list, so one key invalidates all of it. */
const POSTS_KEY = ['posts'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}

export function usePostMutations() {
    const queryCache = useQueryCache();

    const deletePost = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Post suspended.');
            queryCache.invalidateQueries({ key: POSTS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suspend the post.'));
        },
    });

    const restorePost = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Post restored.');
            queryCache.invalidateQueries({ key: POSTS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the post.'));
        },
    });

    /**
     * The count comes from the request, not the response: `bulkDelete()`
     * flashes its total into a redirect this client discards. The two only
     * disagree if a row was already trashed by someone else mid-selection,
     * and the list refresh that follows tells that story better than the
     * toast could.
     */
    const bulkDeletePosts = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'post', 'posts')} suspended.`);
            queryCache.invalidateQueries({ key: POSTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the selected posts.'),
            );
        },
    });

    const bulkRestorePosts = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(`${pluralize(count, 'post', 'posts')} restored.`);
            queryCache.invalidateQueries({ key: POSTS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected posts.'),
            );
        },
    });

    return { deletePost, restorePost, bulkDeletePosts, bulkRestorePosts };
}
