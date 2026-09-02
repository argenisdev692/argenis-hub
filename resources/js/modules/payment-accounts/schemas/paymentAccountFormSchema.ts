import { z } from 'zod';
import type {
    PaymentAccount,
    PaymentAccountWritePayload,
    PaymentMethod,
} from '../types';

/**
 * The client-side mirror of `StorePaymentAccountData::rules()`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 */

const IBAN_PATTERN = /^[A-Z]{2}[0-9A-Z \-]{8,60}$/i;
const BIC_PATTERN = /^[A-Za-z0-9]{8,11}$/;
const CURRENCY_PATTERN = /^[A-Za-z]{3}$/;

/** The literal values `PaymentMethod` allows — one source for schema + guard. */
export const PAYMENT_METHOD_VALUES = [
    'REMITLY',
    'BANK_TRANSFER',
    'WISE',
    'PAYPAL',
    'STRIPE',
    'CASH',
    'OTHER',
] as const satisfies readonly PaymentMethod[];

/** Narrows the reka `Select` model value (typed `unknown`) back to the enum. */
export function isPaymentMethod(value: unknown): value is PaymentMethod {
    return (
        typeof value === 'string' &&
        (PAYMENT_METHOD_VALUES as readonly string[]).includes(value)
    );
}

export const paymentAccountFormSchema = z
    .object({
        method: z.enum(PAYMENT_METHOD_VALUES),
        label: z
            .string()
            .trim()
            .min(1, 'Label is required.')
            .max(255, 'Label must be 255 characters or fewer.'),
        /** Empty means "settles any currency" — a real, deliberate option. */
        currency: z
            .string()
            .trim()
            .refine(
                (value) => value === '' || CURRENCY_PATTERN.test(value),
                'Use the 3-letter ISO code (e.g. “EUR”), or leave it blank for any.',
            ),
        beneficiary: z
            .string()
            .trim()
            .max(255, 'Beneficiary must be 255 characters or fewer.'),
        bank_name: z
            .string()
            .trim()
            .max(255, 'Bank name must be 255 characters or fewer.'),
        iban: z
            .string()
            .trim()
            .max(64, 'IBAN must be 64 characters or fewer.')
            .refine(
                (value) => value === '' || IBAN_PATTERN.test(value),
                'Enter a valid IBAN, e.g. PT50 0036 0011 9910 0063 053 49.',
            ),
        bic: z
            .string()
            .trim()
            .refine(
                (value) => value === '' || BIC_PATTERN.test(value),
                'A BIC/SWIFT code is 8 or 11 letters and digits.',
            ),
        account_number: z
            .string()
            .trim()
            .max(64, 'Account number must be 64 characters or fewer.'),
        routing_number: z
            .string()
            .trim()
            .max(64, 'Routing number must be 64 characters or fewer.'),
        holder_email: z
            .string()
            .trim()
            .max(255, 'Email must be 255 characters or fewer.')
            .refine(
                (value) => value === '' || z.email().safeParse(value).success,
                'Enter a valid email address.',
            ),
        holder_phone: z
            .string()
            .trim()
            .max(32, 'Phone must be 32 characters or fewer.'),
        instructions: z
            .string()
            .trim()
            .max(2000, 'Instructions must be 2000 characters or fewer.'),
        is_default: z.boolean(),
        is_active: z.boolean(),
        sort_order: z.number().int().min(0).max(65535),
    })
    /**
     * Mirrors `required_without_all:account_number,holder_email,holder_phone`.
     * An account with no identifier at all cannot be printed on an invoice, so
     * it would silently produce a PDF with an empty "how to pay" block.
     */
    .refine(
        (values) =>
            values.iban !== '' ||
            values.account_number !== '' ||
            values.holder_email !== '' ||
            values.holder_phone !== '',
        {
            message:
                'Give at least one way to receive money: IBAN, account number, email or phone.',
            path: ['iban'],
        },
    );

export type PaymentAccountFormValues = z.infer<typeof paymentAccountFormSchema>;

export function emptyPaymentAccountFormValues(): PaymentAccountFormValues {
    return {
        method: 'BANK_TRANSFER',
        label: '',
        currency: '',
        beneficiary: '',
        bank_name: '',
        iban: '',
        bic: '',
        account_number: '',
        routing_number: '',
        holder_email: '',
        holder_phone: '',
        instructions: '',
        is_default: false,
        is_active: true,
        sort_order: 0,
    };
}

export function toPaymentAccountFormValues(
    account: PaymentAccount,
): PaymentAccountFormValues {
    return {
        method: account.method,
        label: account.label,
        currency: account.currency ?? '',
        beneficiary: account.beneficiary ?? '',
        bank_name: account.bank_name ?? '',
        iban: account.iban ?? '',
        bic: account.bic ?? '',
        account_number: account.account_number ?? '',
        routing_number: account.routing_number ?? '',
        holder_email: account.holder_email ?? '',
        holder_phone: account.holder_phone ?? '',
        instructions: account.instructions ?? '',
        is_default: account.is_default,
        is_active: account.is_active,
        sort_order: account.sort_order,
    };
}

/** Empty optional strings become `null`; the rest are trimmed at the boundary. */
function nullable(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is the
 * generated write payload: a missing or misnamed key is a compile error here
 * instead of a silently dropped column at runtime.
 */
export function toWritePayload(
    values: PaymentAccountFormValues,
): PaymentAccountWritePayload {
    return {
        method: values.method,
        label: values.label.trim(),
        currency: nullable(values.currency.toUpperCase()),
        beneficiary: nullable(values.beneficiary),
        bank_name: nullable(values.bank_name),
        iban: nullable(values.iban),
        bic: nullable(values.bic.toUpperCase()),
        account_number: nullable(values.account_number),
        routing_number: nullable(values.routing_number),
        holder_email: nullable(values.holder_email),
        holder_phone: nullable(values.holder_phone),
        instructions: nullable(values.instructions),
        is_default: values.is_default,
        is_active: values.is_active,
        sort_order: values.sort_order,
    };
}
