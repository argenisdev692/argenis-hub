import { z } from 'zod';
import type { Client, ClientStatus, ClientWritePayload } from '../types';

/**
 * The client-side mirror of `StoreClientRequest` / `UpdateClientRequest`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. `TextField` always
 * hands back a string, so every optional field is modelled as a (possibly
 * empty) string and collapsed to `null` at the wire boundary in
 * `toWritePayload()`.
 */

const PHONE_PATTERN = /^\+?[0-9]{7,15}$/;
const URL_PATTERN = /^https?:\/\/\S+$/i;
const COUNTRY_CODE_PATTERN = /^[A-Za-z]{2}$/;

/** The literal values `ClientStatus` allows — one source for the schema + guard. */
export const CLIENT_STATUS_VALUES = [
    'DRAFT',
    'ACTIVE',
    'INACTIVE',
] as const satisfies readonly ClientStatus[];

/** Narrows the reka `Select` model value (typed `unknown`) back to `ClientStatus`. */
export function isClientStatus(value: unknown): value is ClientStatus {
    return (
        typeof value === 'string' &&
        (CLIENT_STATUS_VALUES as readonly string[]).includes(value)
    );
}

function isEmail(value: string): boolean {
    return z.email().safeParse(value).success;
}

export const clientFormSchema = z.object({
    client_name: z
        .string()
        .trim()
        .min(1, 'Client name is required.')
        .max(255, 'Client name must be 255 characters or fewer.'),
    status: z.enum(CLIENT_STATUS_VALUES),
    email: z
        .string()
        .trim()
        .max(255, 'Email must be 255 characters or fewer.')
        .refine(
            (value) => value === '' || isEmail(value),
            'Enter a valid email address.',
        ),
    phone: z
        .string()
        .trim()
        .min(1, 'Phone is required.')
        .regex(
            PHONE_PATTERN,
            'Enter 7–15 digits, optionally starting with “+”.',
        ),
    address: z
        .string()
        .trim()
        .max(255, 'Address must be 255 characters or fewer.'),
    country: z
        .string()
        .trim()
        .max(100, 'Country must be 100 characters or fewer.'),
    country_code: z
        .string()
        .trim()
        .refine(
            (value) => value === '' || COUNTRY_CODE_PATTERN.test(value),
            'Use the 2-letter ISO country code (e.g. “US”).',
        ),
    tax_id: z.string().trim().max(50, 'Tax ID must be 50 characters or fewer.'),
    nif: z.string().trim().max(50, 'NIF must be 50 characters or fewer.'),
    website: z
        .string()
        .trim()
        .max(255, 'Website must be 255 characters or fewer.')
        .refine(
            (value) => value === '' || URL_PATTERN.test(value),
            'Enter a full URL starting with http:// or https://.',
        ),
    facebook_link: z
        .string()
        .trim()
        .max(255, 'Link must be 255 characters or fewer.')
        .refine(
            (value) => value === '' || URL_PATTERN.test(value),
            'Enter a full URL starting with http:// or https://.',
        ),
    instagram_link: z
        .string()
        .trim()
        .max(255, 'Link must be 255 characters or fewer.')
        .refine(
            (value) => value === '' || URL_PATTERN.test(value),
            'Enter a full URL starting with http:// or https://.',
        ),
    linkedin_link: z
        .string()
        .trim()
        .max(255, 'Link must be 255 characters or fewer.')
        .refine(
            (value) => value === '' || URL_PATTERN.test(value),
            'Enter a full URL starting with http:// or https://.',
        ),
    twitter_link: z
        .string()
        .trim()
        .max(255, 'Link must be 255 characters or fewer.')
        .refine(
            (value) => value === '' || URL_PATTERN.test(value),
            'Enter a full URL starting with http:// or https://.',
        ),
    notes: z
        .string()
        .trim()
        .max(5000, 'Notes must be 5000 characters or fewer.'),
});

export type ClientFormValues = z.infer<typeof clientFormSchema>;

export function emptyClientFormValues(): ClientFormValues {
    return {
        client_name: '',
        status: 'DRAFT',
        email: '',
        phone: '',
        address: '',
        country: '',
        country_code: '',
        tax_id: '',
        nif: '',
        website: '',
        facebook_link: '',
        instagram_link: '',
        linkedin_link: '',
        twitter_link: '',
        notes: '',
    };
}

export function toClientFormValues(client: Client): ClientFormValues {
    return {
        client_name: client.client_name,
        status: client.status,
        email: client.email ?? '',
        phone: client.phone,
        address: client.address ?? '',
        country: client.country ?? '',
        country_code: client.country_code ?? '',
        tax_id: client.tax_id ?? '',
        nif: client.nif ?? '',
        website: client.website ?? '',
        facebook_link: client.facebook_link ?? '',
        instagram_link: client.instagram_link ?? '',
        linkedin_link: client.linkedin_link ?? '',
        twitter_link: client.twitter_link ?? '',
        notes: client.notes ?? '',
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
export function toWritePayload(values: ClientFormValues): ClientWritePayload {
    return {
        client_name: values.client_name.trim(),
        status: values.status,
        email: nullable(values.email),
        phone: values.phone.trim(),
        address: nullable(values.address),
        country: nullable(values.country),
        country_code: nullable(values.country_code.toUpperCase()),
        tax_id: nullable(values.tax_id),
        nif: nullable(values.nif),
        website: nullable(values.website),
        facebook_link: nullable(values.facebook_link),
        instagram_link: nullable(values.instagram_link),
        linkedin_link: nullable(values.linkedin_link),
        twitter_link: nullable(values.twitter_link),
        notes: nullable(values.notes),
    };
}
