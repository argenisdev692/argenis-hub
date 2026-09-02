import { useQueryCache } from '@pinia/colada';
import type { Ref } from 'vue';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import type { FormTarget } from '@/common/form';
import { useAppForm } from '@/common/form';
import { store, update } from '@/routes/availability-rules';
import {
    availabilityRuleFormSchema,
    emptyAvailabilityRuleFormValues,
    toAvailabilityRuleFormValues,
    toAvailabilityRuleWritePayload,
} from '../schemas/availabilityRuleFormSchema';
import type { AvailabilityRule } from '../types';
import { AVAILABILITY_RULES_KEY } from './useAvailabilityRules';

export type AvailabilityRuleFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed and at submit time.
     * `null` means "create": the form starts from the default working day and
     * posts to `store`. Anything else means "edit": the form seeds from the
     * record and PUTs to `update`.
     */
    rule: () => AvailabilityRule | null;
};

/**
 * One form, two endpoints.
 *
 * ## Why this submits through Inertia rather than a Pinia Colada mutation
 *
 * The write routes answer with `back()->with('success')`, which is an Inertia
 * response, not a JSON one. Going through `useAppForm`'s Inertia path gets CSRF
 * and — the reason that actually matters — a 422 projected back onto the
 * offending field. `AvailabilityRuleData::noOverlapRule()` reports a conflicting
 * slot on `start_time`, and that message is only useful sitting under the start
 * time input; as a toast it names no field at all.
 *
 * The list, however, is Pinia Colada server state and does not re-render on an
 * Inertia visit, so the cache is invalidated by hand once the write lands.
 *
 * Unlike the blog-category form there is no file here, so `forceFormData` stays
 * off and the update goes out as a real `PUT` rather than a `_method` spoof.
 */
export function useAvailabilityRuleForm({
    open,
    rule,
}: AvailabilityRuleFormOptions) {
    const queryCache = useQueryCache();

    const form = useAppForm({
        defaultValues: emptyAvailabilityRuleFormValues(),
        schema: availabilityRuleFormSchema,
        submit: {
            /**
             * A getter because `useAppForm` resolves the target at submit time,
             * which is what lets create and edit share one form instance — the
             * same reason `transform` is a callback rather than a value.
             */
            get target(): FormTarget {
                const current = rule();

                return current ? update(current.uuid) : store();
            },
            transform: toAvailabilityRuleWritePayload,
            // Silenced here so the toast can name what actually happened; the
            // wording is decided in `onSuccess`, where the mode is still known.
            successMessage: null,
            onSuccess: () => {
                toast.success(
                    rule()
                        ? 'Availability rule updated.'
                        : 'Availability rule created.',
                );
                queryCache.invalidateQueries({ key: AVAILABILITY_RULES_KEY });
                open.value = false;
            },
        },
    });

    /**
     * Re-seeded on open rather than on close: a failed submit leaves the
     * operator's input in place so they can read the overlap error and adjust
     * the hours without retyping the whole slot, and the next open starts from
     * the row that is actually being edited.
     */
    watch(open, (isOpen) => {
        if (isOpen) {
            const current = rule();

            form.reset(
                current
                    ? toAvailabilityRuleFormValues(current)
                    : emptyAvailabilityRuleFormValues(),
            );
        }
    });

    return form;
}
