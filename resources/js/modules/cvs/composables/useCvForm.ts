import { useQueryCache } from '@pinia/colada';
import type { Ref } from 'vue';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import type { FormTarget } from '@/common/form';
import { useAppForm } from '@/common/form';
import { store, update } from '@/routes/cvs';
import {
    cvModeAwareSchema,
    emptyCvFormValues,
    toCvFormValues,
    toCvWritePayload,
} from '../schemas/cvFormSchema';
import type { Cv } from '../types';
import { CVS_KEY } from './useCvs';

export type CvFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed, on every
     * validation pass and at submit time. `null` means "create": the form
     * starts empty, requires a file and posts to `store`. Anything else means
     * "edit": the form seeds from the record, treats the file as optional and
     * posts to `update`.
     */
    cv: () => Cv | null;
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
 * matters — a 422 projected back onto the offending field, so
 * `CreateCvHandler`'s "A CV file (PDF or Markdown) is required." surfaces under
 * the dropzone instead of as a toast.
 *
 * The list, however, is Pinia Colada server state and does not re-render on an
 * Inertia visit, so the cache is invalidated by hand once the write lands.
 *
 * ## Why the edit mode POSTs
 *
 * PHP does not populate `$_FILES` for a `PUT` body. The module registers an
 * explicit `POST /cvs/{uuid}` alias (`cvs.update.post`) behind the same
 * `permission:UPDATE_CVS` middleware for exactly this, so the upload rides a
 * real POST — no `_method` spoof needed, and no second interpretation of the
 * request for Laravel to get right.
 */
export function useCvForm({ open, cv }: CvFormOptions) {
    const queryCache = useQueryCache();

    const form = useAppForm({
        defaultValues: emptyCvFormValues(),
        schema: cvModeAwareSchema(() => cv() !== null),
        submit: {
            /**
             * A getter because `useAppForm` resolves the target at submit
             * time, which is what lets create and edit share one form instance.
             */
            get target(): FormTarget {
                const current = cv();

                return current
                    ? { url: update.url(current.uuid), method: 'post' }
                    : store();
            },
            transform: toCvWritePayload,
            // A `File` cannot ride a JSON body; Inertia needs the multipart path.
            forceFormData: true,
            // Silenced here so the toast can name what actually happened; the
            // wording is decided in `onSuccess`, where the mode is still known.
            successMessage: null,
            onSuccess: () => {
                toast.success(cv() ? 'CV updated.' : 'CV uploaded.');
                queryCache.invalidateQueries({ key: CVS_KEY });
                open.value = false;
            },
        },
    });

    /**
     * Re-seeded on open rather than on close: a failed submit leaves the
     * operator's input in place so they can read the error and retry without
     * retyping it, and the next open starts from the row that is actually being
     * edited.
     */
    watch(open, (isOpen) => {
        if (isOpen) {
            const current = cv();

            form.reset(current ? toCvFormValues(current) : emptyCvFormValues());
        }
    });

    return form;
}
