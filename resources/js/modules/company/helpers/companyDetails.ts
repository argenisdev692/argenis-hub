import type { CompanyDetail, CompanyProfile } from '../types';

/**
 * The read-only projections behind each card on the company screen.
 *
 * Kept out of the templates so the "what does this section show" question has a
 * single answer per section, and so the empty-value handling ("—" rather than a
 * blank row) is applied in one place instead of per `<td>`.
 */

/** `null` and whitespace-only values collapse to null so the UI renders "—". */
function present(value: string | null): string | null {
    const trimmed = value?.trim();

    return trimmed ? trimmed : null;
}

export function identityDetails(company: CompanyProfile): CompanyDetail[] {
    return [
        { label: 'Company name', value: present(company.company_name) },
        { label: 'Legal name', value: present(company.legal_name) },
        {
            label: 'Description',
            value: present(company.description),
            multiline: true,
        },
    ];
}

/**
 * Re-validates a stored URL before it becomes an `href`.
 *
 * Both the Zod schema and `UpdateCompanyData`'s `#[Url(['http', 'https'])]`
 * already reject anything else, so this should never fire — which is exactly
 * why it is here. These values are attacker-influenced in the sense that any
 * operator with UPDATE_COMPANY_DATA writes them, they land in the DOM as a
 * navigable link, and a row predating the current validation (or written by a
 * future import path that skips it) would render `javascript:` as a live link.
 * Checking at the point of rendering costs one URL parse and closes that off
 * regardless of how the value got into the column.
 */
export function externalHref(value: string | null): string | null {
    const candidate = present(value);

    if (!candidate) {
        return null;
    }

    try {
        const url = new URL(candidate);

        return url.protocol === 'http:' || url.protocol === 'https:'
            ? url.toString()
            : null;
    } catch {
        return null;
    }
}

export function contactDetails(company: CompanyProfile): CompanyDetail[] {
    const website = present(company.website);
    const email = present(company.email);
    const phone = present(company.phone);

    return [
        { label: 'Website', value: website, href: externalHref(website) },
        {
            label: 'Email',
            value: email,
            href: email ? `mailto:${email}` : null,
        },
        {
            label: 'Phone',
            value: phone,
            // Strip spacing the operator typed for readability; `tel:` wants
            // the dialable string, not the pretty one.
            href: phone ? `tel:${phone.replace(/[^\d+]/g, '')}` : null,
        },
    ];
}

export function addressDetails(company: CompanyProfile): CompanyDetail[] {
    return [
        { label: 'Address', value: present(company.address) },
        { label: 'Address line 2', value: present(company.address_2) },
        { label: 'Postal code', value: present(company.zip_code) },
        { label: 'City', value: present(company.city) },
        { label: 'State / region', value: present(company.state) },
        { label: 'Country', value: present(company.country) },
        { label: 'Country code', value: present(company.country_code) },
    ];
}

export function fiscalDetails(company: CompanyProfile): CompanyDetail[] {
    return [
        { label: 'NIF / NIPC', value: present(company.nif_nipc) },
        { label: 'NIE', value: present(company.nie) },
        { label: 'Beneficiary', value: present(company.bank_beneficiary) },
        { label: 'Bank name', value: present(company.bank_name) },
        { label: 'BIC / SWIFT', value: present(company.bank_bic) },
        { label: 'IBAN', value: present(company.bank_iban) },
        {
            label: 'Invoice notes',
            value: present(company.invoice_notes),
            multiline: true,
        },
    ];
}

/**
 * The postal address as one line, matching how `CompanyAddressData::formatted`
 * composes it server-side: postal code and city share a segment, everything
 * else gets its own, and empty parts are dropped so there is no dangling comma.
 */
export function formattedAddress(company: CompanyProfile): string | null {
    const locality = [present(company.zip_code), present(company.city)]
        .filter(Boolean)
        .join(' ');

    const parts = [
        present(company.address),
        present(company.address_2),
        locality || null,
        present(company.state),
        present(company.country),
    ].filter((part): part is string => Boolean(part));

    return parts.length > 0 ? parts.join(', ') : null;
}

/**
 * Whether a coordinate pair was captured. Both halves are required — a latitude
 * without a longitude points at the Gulf of Guinea, not at the office.
 */
export function hasCoordinates(company: CompanyProfile): boolean {
    return company.latitude !== null && company.longitude !== null;
}
