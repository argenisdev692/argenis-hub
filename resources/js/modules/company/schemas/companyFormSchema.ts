import { z } from 'zod';
import type { CompanyProfile, CompanyUpdatePayload } from '../types';

/**
 * The client-side mirror of `Modules\Company\Application\DTOs\UpdateCompanyData`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. When a rule changes
 * in the PHP Data object it must change here as well; a stricter client is
 * merely annoying, a looser one produces a 422 the user cannot see coming.
 *
 * ## Why every text field is a string, never `string | null`
 *
 * An `<input>` yields `''` when cleared, never `null`. Modelling the form as
 * nullable would mean every field carrying two representations of "empty" and
 * every comparison having to handle both. Instead the form is all-strings and
 * `toUpdatePayload()` performs the single `'' -> null` translation at the wire
 * boundary, which is also the only place that translation is meaningful.
 */

const text = (max: number, label: string) =>
    z.string().trim().max(max, `${label} must be ${max} characters or fewer.`);

/**
 * Parsed with the WHATWG `URL` constructor rather than a regex, then narrowed
 * to the two schemes the backend's `#[Url(['http', 'https'])]` accepts —
 * `z.url()` alone would happily pass `mailto:` and `javascript:`.
 */
function isHttpUrl(value: string): boolean {
    try {
        const { protocol } = new URL(value);

        return protocol === 'http:' || protocol === 'https:';
    } catch {
        return false;
    }
}

const httpUrl = (max: number, label: string) =>
    text(max, label).refine((value) => value === '' || isHttpUrl(value), {
        message: 'Enter a full URL starting with http:// or https://.',
    });

const optionalEmail = (max: number) =>
    text(max, 'Email').refine(
        (value) => value === '' || z.email().safeParse(value).success,
        { message: 'Enter a valid email address.' },
    );

/**
 * Written by the Places selection, never typed. Bounds-checked anyway: an
 * unattended field is exactly the one nobody notices going wrong, which is the
 * same reasoning `UpdateCompanyData` gives for checking it twice server-side.
 */
const latitude = z
    .number()
    .min(-90, 'Latitude must be between -90 and 90.')
    .max(90, 'Latitude must be between -90 and 90.')
    .nullable();

const longitude = z
    .number()
    .min(-180, 'Longitude must be between -180 and 180.')
    .max(180, 'Longitude must be between -180 and 180.')
    .nullable();

export const companyFormSchema = z.object({
    company_name: text(255, 'Company name').min(1, 'Company name is required.'),
    legal_name: text(255, 'Legal name'),
    description: text(5000, 'Description'),

    website: httpUrl(255, 'Website'),
    email: optionalEmail(255),
    phone: text(50, 'Phone'),

    address: text(500, 'Address'),
    address_2: text(500, 'Address line 2'),
    zip_code: text(20, 'Postal code'),
    city: text(255, 'City'),
    state: text(255, 'State'),
    country: text(255, 'Country'),
    country_code: text(2, 'Country code').refine(
        (value) => value === '' || value.length === 2,
        { message: 'Use the two-letter ISO country code, e.g. PT.' },
    ),
    latitude,
    longitude,

    nif_nipc: text(50, 'NIF / NIPC'),
    nie: text(50, 'NIE'),
    bank_beneficiary: text(255, 'Beneficiary'),
    bank_iban: text(64, 'IBAN'),
    bank_bic: text(16, 'BIC / SWIFT'),
    bank_name: text(255, 'Bank name'),
    invoice_notes: text(5000, 'Invoice notes'),

    facebook_link: httpUrl(255, 'Facebook URL'),
    github_link: httpUrl(255, 'GitHub URL'),
    instagram_link: httpUrl(255, 'Instagram URL'),
    linkedin_link: httpUrl(255, 'LinkedIn URL'),
    tiktok_link: httpUrl(255, 'TikTok URL'),
    twitter_link: httpUrl(255, 'X URL'),
});

export type CompanyFormValues = z.infer<typeof companyFormSchema>;

/** Every editable field name, for the dialogs that render a subset. */
export type CompanyFormField = keyof CompanyFormValues;

/** `null` from the API becomes `''` for the inputs. */
const fromApi = (value: string | null): string => value ?? '';

/** `''` from a cleared input becomes `null` for the API. */
const toApi = (value: string): string | null => {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
};

export function toCompanyFormValues(
    company: CompanyProfile,
): CompanyFormValues {
    return {
        company_name: company.company_name,
        legal_name: fromApi(company.legal_name),
        description: fromApi(company.description),

        website: fromApi(company.website),
        email: fromApi(company.email),
        phone: fromApi(company.phone),

        address: fromApi(company.address),
        address_2: fromApi(company.address_2),
        zip_code: fromApi(company.zip_code),
        city: fromApi(company.city),
        state: fromApi(company.state),
        country: fromApi(company.country),
        country_code: fromApi(company.country_code),
        latitude: company.latitude,
        longitude: company.longitude,

        nif_nipc: fromApi(company.nif_nipc),
        nie: fromApi(company.nie),
        bank_beneficiary: fromApi(company.bank_beneficiary),
        bank_iban: fromApi(company.bank_iban),
        bank_bic: fromApi(company.bank_bic),
        bank_name: fromApi(company.bank_name),
        invoice_notes: fromApi(company.invoice_notes),

        facebook_link: fromApi(company.facebook_link),
        github_link: fromApi(company.github_link),
        instagram_link: fromApi(company.instagram_link),
        linkedin_link: fromApi(company.linkedin_link),
        tiktok_link: fromApi(company.tiktok_link),
        twitter_link: fromApi(company.twitter_link),
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is the
 * generated `UpdateCompanyData`: a missing or misnamed key is a compile error
 * here instead of a silently dropped column at runtime. That check is the whole
 * point of the function, and a `Object.fromEntries` loop would erase it.
 */
export function toUpdatePayload(
    values: CompanyFormValues,
): CompanyUpdatePayload {
    return {
        company_name: values.company_name.trim(),
        legal_name: toApi(values.legal_name),
        description: toApi(values.description),

        website: toApi(values.website),
        email: toApi(values.email),
        phone: toApi(values.phone),

        address: toApi(values.address),
        address_2: toApi(values.address_2),
        zip_code: toApi(values.zip_code),
        city: toApi(values.city),
        state: toApi(values.state),
        country: toApi(values.country),
        country_code: values.country_code.trim().toUpperCase() || null,
        latitude: values.latitude,
        longitude: values.longitude,

        nif_nipc: toApi(values.nif_nipc),
        nie: toApi(values.nie),
        bank_beneficiary: toApi(values.bank_beneficiary),
        bank_iban: toApi(values.bank_iban),
        bank_bic: toApi(values.bank_bic),
        bank_name: toApi(values.bank_name),
        invoice_notes: toApi(values.invoice_notes),

        facebook_link: toApi(values.facebook_link),
        github_link: toApi(values.github_link),
        instagram_link: toApi(values.instagram_link),
        linkedin_link: toApi(values.linkedin_link),
        tiktok_link: toApi(values.tiktok_link),
        twitter_link: toApi(values.twitter_link),
    };
}
