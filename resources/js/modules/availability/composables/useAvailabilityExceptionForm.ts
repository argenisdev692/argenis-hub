import { useQueryCache } from '@pinia/colada';
import type { Ref } from 'vue';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import type { FormTarget } from '@/common/form';
import { useAppForm } from '@/common/form';
import { store, update } from '@/routes/availability-exceptions';
import {
    availabilityExceptionFormSchema,
    emptyAvailabilityExceptionFormValues,
    toAvailabilityExceptionFormValues,
    toAvailabilityExceptionWritePayload,
} from '../schemas/availabilityExceptionFormSchema';
import type { AvailabilityException } from '../types';
import { AVAILABILITY_EXCEPTIONS_KEY } from './useAvailabilityExceptions';

export type AvailabilityExceptionFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed and at submit time.
     * `null` means "create"; anything else means "edit".
     */
    exception: () => AvailabilityException | null;
};

/**
 * One form, two endpoints.
 *
 * ## Why this submits through Inertia rather than a Pinia Colada mutation
 *
 * The write routes answer with `back()->with('success')`, an Inertia response
 * rather than a JSON one. The Inertia path also maps a 422 back onto the
 * offending field, which is what makes the "one active exception per date"
 * unique rule readable: it reports on `date`, and that message only means
 * something sitting under the date input.
 *
 * The list is Pinia Colada server state and does not re-render on an Inertia
 * visit, so the cache is invalidated by hand once the write lands.
 *
 * ## Why the schema takes a getter
 *
 * `AvailabilityExceptionData::rules()` applies `after_or_equal:today` on create
 * only, so an already-past exception stays editable. One dialog instance serves
 * both modes without ever unmounting, so the mode has to be read at validation
 * time — hence the getter rather than a boolean captured here. See
 * `availabilityExceptionFormSchema`.
 */
export function useAvailabilityExceptionForm({
    open,
    exception,
}: AvailabilityExceptionFormOptions) {
    const queryCache = useQueryCache();

    const form = useAppForm({
        defaultValues: emptyAvailabilityExceptionFormValues(),
        schema: availabilityExceptionFormSchema(() => exception() !== null),
        submit: {
            /**
             * A getter because `useAppForm` resolves the target at submit time,
             * which is what lets create and edit share one form instance.
             */
            get target(): FormTarget {
                const current = exception();

                return current ? update(current.uuid) : store();
            },
            transform: toAvailabilityExceptionWritePayload,
            successMessage: null,
            onSuccess: () => {
                toast.success(
                    exception()
                        ? 'Date exception updated.'
                        : 'Date exception created.',
                );
                queryCache.invalidateQueries({
                    key: AVAILABILITY_EXCEPTIONS_KEY,
                });
                open.value = false;
            },
        },
    });

    /**
     * Re-seeded on open rather than on close: a failed submit leaves the
     * operator's input in place so they can read the error and retry without
     * retyping it, and the next open starts from the row actually being edited.
     */
    watch(open, (isOpen) => {
        if (isOpen) {
            const current = exception();

            form.reset(
                current
                    ? toAvailabilityExceptionFormValues(current)
                    : emptyAvailabilityExceptionFormValues(),
            );
        }
    });

    return form;
}
