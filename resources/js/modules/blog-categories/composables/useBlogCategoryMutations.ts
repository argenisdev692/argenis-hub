import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
} from '@/routes/blog-categories';
import { BLOG_CATEGORIES_KEY } from './useBlogCategories';

/**
 * The row and bulk actions the list offers. Create and update are absent on
 * purpose: they live in `useBlogCategoryForm`, which submits through Inertia
 * because they carry a file upload and want the redirect-and-flash round trip
 * that maps a 422 back onto the offending field.
 *
 * ## On the responses these endpoints return
 *
 * Unlike the JSON CRUD surfaces the Services and Clients modules talk to, these
 * four routes answer with `back()` — they were written for an Inertia form.
 * Sent with `Accept: application/json`, the 302 is followed by `fetch` (as a
 * GET, per the fetch spec) back to `GET /blog-categories`, which serves its
 * JSON branch. So the happy path resolves with a list payload nobody reads, and
 * a 422 or 403 still arrives as JSON with a usable message — which is the part
 * that matters.
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

export function useBlogCategoryMutations() {
    const queryCache = useQueryCache();

    const deleteBlogCategory = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Blog category suspended.');
            queryCache.invalidateQueries({ key: BLOG_CATEGORIES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to suspend the blog category.'),
            );
        },
    });

    const restoreBlogCategory = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Blog category restored.');
            queryCache.invalidateQueries({ key: BLOG_CATEGORIES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the blog category.'),
            );
        },
    });

    /**
     * The count comes from the request, not the response: `bulkDelete()`
     * flashes its total into a redirect this client discards. The two only
     * disagree if a row was already trashed by someone else mid-selection, and
     * the list refresh that follows tells that story better than the toast
     * could.
     */
    const bulkDeleteBlogCategories = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(
                `${pluralize(count, 'blog category', 'blog categories')} suspended.`,
            );
            queryCache.invalidateQueries({ key: BLOG_CATEGORIES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to suspend the selected blog categories.',
                ),
            );
        },
    });

    const bulkRestoreBlogCategories = useMutation({
        mutation: async (uuids: string[]) => {
            await httpJson<unknown>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            });

            return uuids.length;
        },
        onSuccess(count: number) {
            toast.success(
                `${pluralize(count, 'blog category', 'blog categories')} restored.`,
            );
            queryCache.invalidateQueries({ key: BLOG_CATEGORIES_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to restore the selected blog categories.',
                ),
            );
        },
    });

    return {
        deleteBlogCategory,
        restoreBlogCategory,
        bulkDeleteBlogCategories,
        bulkRestoreBlogCategories,
    };
}
