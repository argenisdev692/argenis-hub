import { z } from 'zod';
import { isoPlusDays, todayIso } from '../helpers/invoicePresentation';
import type {
    BillingUnit,
    Currency,
    InvoiceDetail,
    InvoiceItemKind,
    InvoiceWritePayload,
    PaymentMethod,
    TaxMode,
} from '../types';

/**
 * The client-side mirror of `Modules\Invoices\Application\DTOs\InvoiceData`
 * and its nested `InvoiceItemData`.
 *
 * Every rule here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. Where the two could
 * drift the comment names the PHP rule it mirrors, so a change on one side has
 * an obvious counterpart on the other.
 */

/**
 * The enum vocabularies, in the order the pickers should offer them.
 *
 * `satisfies` rather than a type annotation: it keeps the literal tuple type
 * that `z.enum()` needs while still failing the build the moment a value stops
 * existing in the generated union, so a renamed case on the server cannot
 * quietly survive in the form.
 *
 * `GET .../form-options` also returns the item kinds, units and methods. They
 * are declared here instead of read from that response because a `<Select>`
 * cannot wait on a fetch to know what it is allowed to hold — the schema needs
 * them at module load, and the round trip would buy nothing the generated types
 * do not.
 */
export const INVOICE_ITEM_KIND_VALUES = [
    'SERVICE',
    'COURSE',
    'VIDEO',
    'CUSTOM',
] as const satisfies readonly InvoiceItemKind[];

export const BILLING_UNIT_VALUES = [
    'UNIT',
    'HOUR',
    'SESSION',
    'DAY',
    'MONTH',
] as const satisfies readonly BillingUnit[];

export const PAYMENT_METHOD_VALUES = [
    'BANK_TRANSFER',
    'REMITLY',
    'WISE',
    'PAYPAL',
    'STRIPE',
    'CASH',
    'OTHER',
] as const satisfies readonly PaymentMethod[];

export const TAX_MODE_VALUES = [
    'EXEMPT',
    'PERCENT',
] as const satisfies readonly TaxMode[];

/** `Shared\Domain\Enums\Currency` — the only codes the invoice PDF can render. */
export const CURRENCY_VALUES = [
    'EUR',
    'USD',
    'GBP',
] as const satisfies readonly Currency[];

/**
 * Narrowing guards for the reka `Select` handlers.
 *
 * `@update:model-value` hands back `AcceptableValue`, which is wider than the
 * union the field holds. These turn that into a checked narrowing instead of
 * the `as` cast that would let a typo through silently (`§13`, zero `any`).
 */
export function isInvoiceItemKind(value: unknown): value is InvoiceItemKind {
    return (
        typeof value === 'string' &&
        (INVOICE_ITEM_KIND_VALUES as readonly string[]).includes(value)
    );
}

export function isBillingUnit(value: unknown): value is BillingUnit {
    return (
        typeof value === 'string' &&
        (BILLING_UNIT_VALUES as readonly string[]).includes(value)
    );
}

export function isPaymentMethod(value: unknown): value is PaymentMethod {
    return (
        typeof value === 'string' &&
        (PAYMENT_METHOD_VALUES as readonly string[]).includes(value)
    );
}

export function isTaxMode(value: unknown): value is TaxMode {
    return (
        typeof value === 'string' &&
        (TAX_MODE_VALUES as readonly string[]).includes(value)
    );
}

export function isCurrency(value: unknown): value is Currency {
    return (
        typeof value === 'string' &&
        (CURRENCY_VALUES as readonly string[]).includes(value)
    );
}

/**
 * One billable line.
 *
 * `quantity` and `unit_price` are numbers, not the strings an `<input>` yields:
 * `InvoiceLineItemsField` parses on change so the running totals preview has
 * something to add up on every keystroke rather than only at submit.
 */
export const invoiceItemFormSchema = z
    .object({
        title: z
            .string()
            .trim()
            .min(1, 'A line needs a title.')
            .max(255, 'Title must be 255 characters or fewer.'),
        description: z
            .string()
            .max(5000, 'Description must be 5000 characters or fewer.'),
        kind: z.enum(INVOICE_ITEM_KIND_VALUES),
        unit: z.enum(BILLING_UNIT_VALUES),
        quantity: z
            .number('Quantity must be a number.')
            .min(0.01, 'Quantity must be at least 0.01.')
            .max(999999, 'Quantity is too large.'),
        unit_price: z
            .number('Unit price must be a number.')
            .min(0, 'Unit price cannot be negative.')
            .max(9999999.99, 'Unit price is too large.'),
        service_uuid: z.string().nullable(),
        product_uuid: z.string().nullable(),
        sort_order: z.number().int().min(0).max(65535),
    })
    // Mirrors `required_if:items.*.kind,COURSE,VIDEO` — a training line with no
    // catalog row behind it would bill a course the catalog never sold.
    .refine(
        (item) =>
            (item.kind !== 'COURSE' && item.kind !== 'VIDEO') ||
            item.product_uuid !== null,
        {
            path: ['product_uuid'],
            message: 'Course and video lines must reference a catalog product.',
        },
    );

export type InvoiceItemFormValues = z.infer<typeof invoiceItemFormSchema>;

export const invoiceFormSchema = z
    .object({
        client_uuid: z.uuid('Choose the client this invoice bills.'),
        product_uuid: z.string().nullable(),
        // `regex:/^\d{1,6}\/\d{4}$/` on the server. The form normalises a bare
        // `14` into `014/2026` before it gets here, so the message can talk
        // about the shape rather than the normalisation.
        invoice_number: z
            .string()
            .trim()
            .regex(/^\d{1,6}\/\d{4}$/, 'Use the form 014/2026.'),
        issue_date: z.iso.date('Choose an issue date.'),
        due_date: z.iso.date('Choose a due date.'),
        // `in:EUR,USD,GBP` on the server. `''` is admitted by the type only so
        // an invoice saved in a currency outside that set opens with the field
        // empty — the operator re-picks it explicitly instead of the form
        // silently switching a legal document to another currency.
        //
        // `length > 0` rather than `!== ''` on purpose: TypeScript infers the
        // latter as a type predicate, Zod narrows the output type with it, and
        // the form could then no longer hold the empty value it is seeded with.
        currency: z
            .union([z.enum(CURRENCY_VALUES), z.literal('')])
            .refine((value) => value.length > 0, {
                message: 'Choose the currency this invoice bills in.',
            }),
        tax_mode: z.enum(TAX_MODE_VALUES),
        tax_rate: z
            .number()
            .min(0, 'A tax rate cannot be negative.')
            .max(100, 'A tax rate cannot exceed 100%.')
            .nullable(),
        tax_label: z
            .string()
            .trim()
            .min(1, 'Name the tax line — "IVA", "VAT", "Exempt".')
            .max(32, 'Tax label must be 32 characters or fewer.'),
        is_paid: z.boolean(),
        payment_method: z.enum(PAYMENT_METHOD_VALUES).nullable(),
        payment_account_uuid: z.string().nullable(),
        transfer_number: z
            .string()
            .max(255, 'Reference must be 255 characters or fewer.'),
        payment_date: z.iso.date('Choose the date it was settled.').nullable(),
        amount_received: z
            .number()
            .min(0, 'Amount received cannot be negative.')
            .max(9999999.99, 'Amount received is too large.')
            .nullable(),
        notes: z.string().max(5000, 'Notes must be 5000 characters or fewer.'),
        additional_notes: z
            .string()
            .max(5000, 'Notes must be 5000 characters or fewer.'),
        items: z
            .array(invoiceItemFormSchema)
            .min(1, 'An invoice needs at least one line.')
            .max(50, 'An invoice can carry at most 50 lines.'),
    })
    // `after_or_equal:issue_date`. Compared as ISO strings, which sort
    // lexicographically for this format and keep time zones out of a
    // comparison between two values that have none.
    .refine((values) => values.due_date >= values.issue_date, {
        path: ['due_date'],
        message: 'The due date cannot fall before the issue date.',
    })
    // The three `required_if:is_paid,true` rules, reported on their own fields
    // so the payment block highlights what is missing rather than the toggle.
    .refine((values) => !values.is_paid || values.payment_method !== null, {
        path: ['payment_method'],
        message: 'A paid invoice needs the method it was settled through.',
    })
    .refine((values) => !values.is_paid || Boolean(values.payment_date), {
        path: ['payment_date'],
        message: 'A paid invoice needs the date it was settled.',
    })
    .refine((values) => !values.is_paid || values.amount_received !== null, {
        path: ['amount_received'],
        message: 'A paid invoice needs the amount that was received.',
    })
    // `tax_rate` is nullable in the DTO, but a PERCENT invoice with no rate
    // silently bills zero tax — which reads as a bug, not as a choice.
    .refine(
        (values) => values.tax_mode !== 'PERCENT' || values.tax_rate !== null,
        { path: ['tax_rate'], message: 'Set the percentage to apply.' },
    );

export type InvoiceFormValues = z.infer<typeof invoiceFormSchema>;

/** A blank line, ready for the operator to fill in. */
export function emptyInvoiceItem(sortOrder: number): InvoiceItemFormValues {
    return {
        title: '',
        description: '',
        kind: 'CUSTOM',
        unit: 'UNIT',
        quantity: 1,
        unit_price: 0,
        service_uuid: null,
        product_uuid: null,
        sort_order: sortOrder,
    };
}

/**
 * A new invoice, pre-filled with the defaults that are right far more often
 * than not: issued today, due in 30 days, tax-exempt.
 *
 * `invoice_number` starts empty and is filled in by `useInvoiceNumber` from
 * `GET .../next-number` once the dialog opens — guessing the sequence in the
 * browser would race every other operator on the same year.
 */
export function emptyInvoiceFormValues(): InvoiceFormValues {
    const issuedToday = todayIso();

    return {
        client_uuid: '',
        product_uuid: null,
        invoice_number: '',
        issue_date: issuedToday,
        due_date: isoPlusDays(issuedToday, 30),
        currency: 'EUR',
        tax_mode: 'EXEMPT',
        tax_rate: 0,
        tax_label: 'IVA',
        is_paid: false,
        payment_method: null,
        payment_account_uuid: null,
        transfer_number: '',
        payment_date: null,
        amount_received: null,
        notes: '',
        additional_notes: '',
        items: [emptyInvoiceItem(0)],
    };
}

/**
 * Seeds the edit form from a fetched detail record.
 *
 * Nullable text columns are flattened to `''` rather than kept as `null`: an
 * `<input>` bound to `null` renders the string "null", and `toInvoiceWritePayload`
 * turns the empties back into the nulls the DTO expects on the way out.
 */
export function toInvoiceFormValues(invoice: InvoiceDetail): InvoiceFormValues {
    return {
        client_uuid: invoice.client_uuid ?? '',
        product_uuid: invoice.product_uuid,
        invoice_number: invoice.invoice_number,
        issue_date: invoice.issue_date.slice(0, 10),
        due_date: invoice.due_date.slice(0, 10),
        currency: isCurrency(invoice.currency) ? invoice.currency : '',
        tax_mode: invoice.tax_mode,
        tax_rate: invoice.tax_rate,
        tax_label: invoice.tax_label,
        is_paid: invoice.is_paid,
        payment_method: invoice.payment_method,
        payment_account_uuid: invoice.payment_account_uuid,
        transfer_number: invoice.transfer_number ?? '',
        payment_date: invoice.payment_date?.slice(0, 10) ?? null,
        amount_received: invoice.amount_received,
        notes: invoice.notes ?? '',
        additional_notes: invoice.additional_notes ?? '',
        items: invoice.items.map((item, index) => ({
            title: item.title,
            description: item.description ?? '',
            kind: item.kind,
            unit: item.unit,
            quantity: item.quantity,
            unit_price: item.unit_price,
            service_uuid: item.service_uuid,
            product_uuid: item.product_uuid,
            sort_order: item.sort_order > 0 ? item.sort_order : index,
        })),
    };
}

/** Turns `''` back into the `null` the DTO's nullable columns expect. */
function nullIfBlank(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

/**
 * Projects the form onto the exact JSON body `POST` / `PUT
 * /data/admin/invoices` accepts.
 *
 * The payment block is zeroed out when `is_paid` is false rather than sent as
 * whatever the operator typed before flipping the toggle back: those fields
 * stay mounted so the values survive a mis-click, but an unpaid invoice that
 * carries a payment date would print a PAYMENT RECEIVED block on the PDF for a
 * payment that never happened.
 *
 * `sort_order` is renumbered from the array index, which is what makes drag
 * reordering in `InvoiceLineItemsField` actually persist.
 */
export function toInvoiceWritePayload(
    values: InvoiceFormValues,
): InvoiceWritePayload {
    const isPaid = values.is_paid;

    return {
        client_uuid: values.client_uuid,
        product_uuid: values.product_uuid,
        invoice_number: values.invoice_number.trim(),
        issue_date: values.issue_date,
        due_date: values.due_date,
        currency: values.currency,
        tax_mode: values.tax_mode,
        tax_rate: values.tax_mode === 'PERCENT' ? values.tax_rate : 0,
        tax_label: values.tax_label.trim(),
        is_paid: isPaid,
        payment_method: isPaid ? values.payment_method : null,
        payment_account_uuid: isPaid ? values.payment_account_uuid : null,
        transfer_number: isPaid ? nullIfBlank(values.transfer_number) : null,
        payment_date: isPaid ? nullIfBlank(values.payment_date ?? '') : null,
        amount_received: isPaid ? values.amount_received : null,
        notes: nullIfBlank(values.notes),
        additional_notes: nullIfBlank(values.additional_notes),
        items: values.items.map((item, index) => ({
            title: item.title.trim(),
            description: nullIfBlank(item.description),
            kind: item.kind,
            unit: item.unit,
            quantity: item.quantity,
            unit_price: item.unit_price,
            service_uuid: item.kind === 'SERVICE' ? item.service_uuid : null,
            product_uuid: item.product_uuid,
            sort_order: index,
        })),
    };
}
