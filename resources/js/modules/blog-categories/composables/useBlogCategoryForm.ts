import { useQueryCache } from '@pinia/colada';
import type { Ref } from 'vue';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import type { FormTarget } from '@/common/form';
import { useAppForm } from '@/common/form';
import { store, update } from '@/routes/blog-categories';
import {
    blogCategoryFormSchema,
    emptyBlogCategoryFormValues,
    toBlogCategoryFormValues,
    toWritePayload,
} from '../schemas/blogCategoryFormSchema';
import type { BlogCategory } from '../types';
import { BLOG_CATEGORIES_KEY } from './useBlogCategories';

export type BlogCategoryFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed and at submit
     * time. `null` means "create": the form starts empty and posts to `store`.
     * Anything else means "edit": the form seeds from the record and posts to
     * `update`.
     */
    category: () => BlogCategory | null;
};

/**
 * One form, two endpoints.
 *
 * ## Why this submits through Inertia rather than a Pinia Colada mutation
 *
 * The write routes carry a file and answer with `back()->with('success')`,
 * which is an Inertia response, not a JSON one. Going through `useAppForm`'s
 * Inertia path gets three things a `fetch` mutation would have to rebuild:
 * multipart encoding of the `File`, CSRF, and — the reason that actually
 * matters — a 422 projected back onto the offending field, so the `unique`
 * name rule surfaces under the input instead of as a toast.
 *
 * The list, however, is Pinia Colada server state and does not re-render on an
 * Inertia visit, so the cache is invalidated by hand once the write lands.
 *
 * ## Why both modes post
 *
 * PHP does not populate `$_FILES` for a `PUT` body, so the update is spoofed
 * through `POST` with a `_method` field (added by `toWritePayload`) exactly as
 * a Blade `@method('PUT')` form does. Laravel's method override runs before
 * routing, so the request still reaches the `PUT` route and its
 * `permission:UPDATE_BLOG_CATEGORIES` middleware.
 */
export function useBlogCategoryForm({
    open,
    category,
}: BlogCategoryFormOptions) {
    const queryCache = useQueryCache();

    const form = useAppForm({
        defaultValues: emptyBlogCategoryFormValues(),
        schema: blogCategoryFormSchema,
        submit: {
            /**
             * A getter because `useAppForm` resolves the target at submit
             * time, which is what lets create and edit share one form instance
             * — the same reason `transform` is a callback rather than a value.
             */
            get target(): FormTarget {
                const current = category();

                return current
                    ? { url: update.url(current.uuid), method: 'post' }
                    : store();
            },
            transform: (values) => toWritePayload(values, category() !== null),
            // A `File` cannot ride a JSON body; Inertia needs the multipart path.
            forceFormData: true,
            // Silenced here so the toast can name what actually happened; the
            // wording is decided in `onSuccess`, where the mode is still known.
            successMessage: null,
            onSuccess: () => {
                toast.success(
                    category()
                        ? 'Blog category updated.'
                        : 'Blog category created.',
                );
                queryCache.invalidateQueries({ key: BLOG_CATEGORIES_KEY });
                open.value = false;
            },
        },
    });

    /**
     * Re-seeded on open rather than on close: a failed submit leaves the
     * operator's input in place so they can read the error and retry without
     * retyping it, and the next open starts from the row that is actually
     * being edited.
     */
    watch(open, (isOpen) => {
        if (isOpen) {
            const current = category();

            form.reset(
                current
                    ? toBlogCategoryFormValues(current)
                    : emptyBlogCategoryFormValues(),
            );
        }
    });

    return form;
}
