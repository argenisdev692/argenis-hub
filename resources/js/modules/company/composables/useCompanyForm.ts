import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import { update } from '@/routes/company';
import {
    companyFormSchema,
    toCompanyFormValues,
    toUpdatePayload,
} from '../schemas/companyFormSchema';
import type { CompanyProfile } from '../types';

/**
 * The company form, in the two shapes the screen needs.
 *
 * ## Why every dialog submits the whole record
 *
 * `PUT /settings/company` resolves `UpdateCompanyData`, which validates the
 * complete editable surface — `company_name` is `#[Required]` there. A dialog
 * that posted only its own three fields would therefore fail validation on
 * `company_name` while editing the bank details, so each form is seeded from
 * the full record and sends all of it. The dialogs differ only in which fields
 * they render; the payload is identical, which is also why they can share one
 * schema instead of five overlapping ones.
 */

export type CompanyFormOptions = {
    /**
     * A getter, not the value. Props are destructured at the call site under
     * the Vue 3.5 style, so a plain value would freeze at first render and the
     * form would re-seed from a stale record after the next Inertia visit.
     */
    company: () => CompanyProfile;
    successMessage?: string;
    onSuccess?: () => void;
};

export function useCompanyForm({
    company,
    successMessage = 'Company details updated.',
    onSuccess,
}: CompanyFormOptions) {
    return useAppForm({
        defaultValues: toCompanyFormValues(company()),
        schema: companyFormSchema,
        submit: {
            target: update(),
            transform: toUpdatePayload,
            successMessage,
            onSuccess,
        },
    });
}

export type CompanySectionFormOptions = CompanyFormOptions & {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
};

/**
 * A company form bound to a dialog's lifetime.
 *
 * The re-seed on open is the part that matters. `useAppForm` snapshots
 * `defaultValues` once, and after a save Inertia hands down a fresh `company`
 * prop — so a dialog opened a second time would otherwise show the values from
 * before the last save, and `isDirty` would compare against them too, arming
 * the discard guard over edits the user had already committed.
 */
export function useCompanySectionForm({
    company,
    open,
    successMessage = 'Company details updated.',
    onSuccess,
}: CompanySectionFormOptions) {
    const form = useCompanyForm({
        company,
        successMessage,
        onSuccess: () => {
            open.value = false;
            onSuccess?.();
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            form.reset(toCompanyFormValues(company()));
        }
    });

    return form;
}
