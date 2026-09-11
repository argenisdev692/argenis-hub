import type { Ref } from 'vue';
import { watch } from 'vue';
import { applyServerErrors, useAppForm } from '@/common/form';
import { HttpError } from '@/lib/http';
import {
    emptyInvoiceFormValues,
    invoiceFormSchema,
    toInvoiceFormValues,
    toInvoiceWritePayload,
} from '../schemas/invoiceFormSchema';
import type { InvoiceDetail } from '../types';
import { useInvoiceMutations } from './useInvoiceMutations';

export type InvoiceFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed and again at submit
     * time. `null` means "create": the form starts empty and `POST`s. Anything
     * else means "edit": the form seeds from the fetched detail and `PUT`s.
     *
     * It is a `InvoiceDetail`, never a list row: the list carries no line
     * items, and seeding the form from it would silently blank every line the
     * operator did not retype.
     */
    invoice: () => InvoiceDetail | null;
    onSuccess?: () => void;
};

/**
 * Turns Laravel's 422 `errors` bag into the one message per field
 * `applyServerErrors` expects.
 *
 * Line errors (`items.2.product_uuid`) are folded onto the `items` field, which
 * is the only line-level error surface the dialog renders — a message keyed to
 * a nested path no `form.Field` is bound to would never be seen.
 */
function toFieldErrors(
    errors: Record<string, string[]>,
): Record<string, string> {
    const fieldErrors: Record<string, string> = {};

    for (const [name, messages] of Object.entries(errors)) {
        const message = messages[0];

        if (!message) {
            continue;
        }

        const line = /^items\.(\d+)\./.exec(name);

        if (line) {
            fieldErrors.items ??= `Line ${Number(line[1]) + 1}: ${message}`;

            continue;
        }

        fieldErrors[name] = message;
    }

    return fieldErrors;
}

/**
 * One form, two endpoints.
 *
 * Submits through Pinia Colada rather than `useAppForm`'s Inertia path because
 * `/data/admin/invoices` answers with JSON, not an Inertia response — the
 * `router` would treat a 201 body as a failed visit. The mutations already own
 * the toasts and the cache invalidation, so all this has to add is the mapping
 * from form values to payload, the create/edit branch, and projecting a 422
 * back onto the fields the server rejected.
 */
export function useInvoiceForm({
    open,
    invoice,
    onSuccess,
}: InvoiceFormOptions) {
    const { createInvoice, updateInvoice } = useInvoiceMutations();

    const form = useAppForm({
        defaultValues: emptyInvoiceFormValues(),
        schema: invoiceFormSchema,
        onSubmit: async (values) => {
            const payload = toInvoiceWritePayload(values);
            const current = invoice();

            try {
                if (current) {
                    await updateInvoice.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createInvoice.mutateAsync(payload);
                }
            } catch (error) {
                // The mutation's own `onError` already toasted the failure.
                // A 422 also names the offending fields — a payment account in
                // another currency, a method the account does not settle, a
                // suspended catalog product — so show each under its input.
                // Swallowed either way so it never reaches `handleSubmit`'s
                // caller and the dialog stays open for another attempt.
                if (
                    error instanceof HttpError &&
                    error.status === 422 &&
                    error.errors
                ) {
                    applyServerErrors(form, toFieldErrors(error.errors));
                }

                return;
            }

            onSuccess?.();
        },
    });

    /**
     * Re-seeded whenever the dialog opens *or* the record it is editing
     * arrives.
     *
     * The second half is what the simpler `watch(open, …)` used elsewhere in
     * this app cannot do here: the edit dialog opens on a row click and the
     * detail request is still in flight at that moment, so seeding only on open
     * would leave the form empty and, worse, mark it pristine — the operator
     * would see a blank invoice and `FormDialog`'s dirty guard would let them
     * close it without warning.
     *
     * Keyed on the uuid rather than the record itself, so a background refetch
     * that returns the same invoice under a new object identity does not reset
     * a form the operator is halfway through editing.
     */
    watch(
        [open, () => invoice()?.uuid ?? null],
        ([isOpen]) => {
            if (!isOpen) {
                return;
            }

            const current = invoice();

            form.reset(
                current
                    ? toInvoiceFormValues(current)
                    : emptyInvoiceFormValues(),
            );
        },
        { immediate: true },
    );

    return form;
}
