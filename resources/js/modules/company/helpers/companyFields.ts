import { SOCIAL_CHANNELS } from '@/common/brand/socialChannels';
import type { CompanyFormField } from '../schemas/companyFormSchema';

/**
 * Every editable field, described once.
 *
 * The section dialogs and the full edit page render the same inputs, so the
 * label, placeholder and autocomplete hint live here rather than in both — the
 * alternative is a form where "NIF / NIPC" is spelled two ways depending on
 * which route the operator took to reach it.
 */
export type CompanyFieldSpec = {
    name: CompanyFormField;
    label: string;
    description?: string;
    placeholder?: string;
    type?: string;
    multiline?: boolean;
    rows?: number;
    required?: boolean;
    autocomplete?: string;
    inputmode?: 'text' | 'email' | 'tel' | 'url' | 'numeric' | 'decimal';
    /** Give the field the full row in a two-column grid. */
    wide?: boolean;
};

/**
 * The spec minus its layout and identity keys, ready to spread onto `TextField`.
 *
 * `name` and `wide` are consumed by the renderer — `name` by `<form.Field>` and
 * `wide` by the grid wrapper — so spreading the whole spec would leak them onto
 * the DOM as stray `name` and `wide` attributes.
 */
export function toControlProps(spec: CompanyFieldSpec) {
    return {
        label: spec.label,
        description: spec.description,
        placeholder: spec.placeholder,
        type: spec.type,
        multiline: spec.multiline,
        rows: spec.rows,
        required: spec.required,
        autocomplete: spec.autocomplete,
        inputmode: spec.inputmode,
    };
}

export const IDENTITY_FIELDS: readonly CompanyFieldSpec[] = [
    {
        name: 'company_name',
        label: 'Company name',
        description: 'The trading name shown across the app and on invoices.',
        placeholder: 'Argenis Hub',
        required: true,
        autocomplete: 'organization',
        wide: true,
    },
    {
        name: 'legal_name',
        label: 'Legal name',
        description: 'The registered entity, if it differs from the above.',
        placeholder: 'Argenis Hub, Lda.',
        wide: true,
    },
    {
        name: 'description',
        label: 'Description',
        description: 'Used on the public site and in email footers.',
        multiline: true,
        rows: 4,
        wide: true,
    },
];

export const CONTACT_FIELDS: readonly CompanyFieldSpec[] = [
    {
        name: 'website',
        label: 'Website',
        placeholder: 'https://example.com',
        type: 'url',
        inputmode: 'url',
        autocomplete: 'url',
        wide: true,
    },
    {
        name: 'email',
        label: 'Email',
        placeholder: 'hello@example.com',
        type: 'email',
        inputmode: 'email',
        autocomplete: 'email',
    },
    {
        name: 'phone',
        label: 'Phone',
        placeholder: '+351 200 000 000',
        type: 'tel',
        inputmode: 'tel',
        autocomplete: 'tel',
    },
];

/**
 * The postal fields only.
 *
 * `latitude` and `longitude` are deliberately absent: they are written by the
 * Places selection and never typed, exactly as `UpdateCompanyData` describes.
 * Rendering them would invite someone to "fix" a coordinate by hand.
 */
export const ADDRESS_FIELDS: readonly CompanyFieldSpec[] = [
    {
        name: 'address',
        label: 'Address',
        placeholder: 'Rua da Alegria 12',
        autocomplete: 'address-line1',
        wide: true,
    },
    {
        name: 'address_2',
        label: 'Address line 2',
        placeholder: 'Floor 3, Office B',
        autocomplete: 'address-line2',
        wide: true,
    },
    {
        name: 'zip_code',
        label: 'Postal code',
        description:
            'Pre-filled from the address lookup; correct it if needed.',
        placeholder: '6200-386',
        autocomplete: 'postal-code',
    },
    {
        name: 'city',
        label: 'City',
        placeholder: 'Covilhã',
        autocomplete: 'address-level2',
    },
    {
        name: 'state',
        label: 'State / region',
        placeholder: 'Castelo Branco',
        autocomplete: 'address-level1',
    },
    {
        name: 'country',
        label: 'Country',
        placeholder: 'Portugal',
        autocomplete: 'country-name',
    },
    {
        name: 'country_code',
        label: 'Country code',
        description: 'Two-letter ISO code.',
        placeholder: 'PT',
        autocomplete: 'country',
    },
];

export const FISCAL_FIELDS: readonly CompanyFieldSpec[] = [
    { name: 'nif_nipc', label: 'NIF / NIPC', placeholder: '500000000' },
    { name: 'nie', label: 'NIE', placeholder: 'X0000000X' },
    {
        name: 'bank_beneficiary',
        label: 'Beneficiary',
        description: 'The account holder exactly as the bank records it.',
        wide: true,
    },
    {
        name: 'bank_name',
        label: 'Bank name',
        placeholder: 'Caixa Geral de Depósitos',
    },
    { name: 'bank_bic', label: 'BIC / SWIFT', placeholder: 'CGDIPTPL' },
    {
        name: 'bank_iban',
        label: 'IBAN',
        placeholder: 'PT50 0000 0000 0000 0000 0000 0',
        wide: true,
    },
    {
        name: 'invoice_notes',
        label: 'Invoice notes',
        description: 'Appended to every invoice — payment terms, VAT notes.',
        multiline: true,
        rows: 4,
        wide: true,
    },
];

/**
 * Derived from the shared channel list so a channel added to the backend enum
 * appears in this form without a second edit here.
 */
export const SOCIAL_FIELDS: readonly CompanyFieldSpec[] = SOCIAL_CHANNELS.map(
    (channel) => ({
        name: channel.field,
        label: channel.label,
        placeholder: channel.placeholder,
        type: 'url',
        inputmode: 'url',
        wide: true,
    }),
);
