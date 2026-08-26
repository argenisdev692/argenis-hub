/**
 * Company module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** The company as the authenticated operator sees it — flat, includes fiscal. */
export type CompanyProfile =
    Modules.Company.Application.DTOs.CompanyProfileData;

/** The three brand marks, already resolved to absolute URLs. */
export type CompanyLogos = Modules.Company.Application.DTOs.CompanyLogosData;

/** The exact body `PUT /settings/company` accepts. */
export type CompanyUpdatePayload =
    Modules.Company.Application.DTOs.UpdateCompanyData;

export type LogoVariant = Modules.Company.Domain.Enums.LogoVariant;

/**
 * One editable region of the company record.
 *
 * The backend takes the whole record on every write, so these exist purely to
 * split the form into dialogs a person can finish in one sitting. Keep the
 * union in step with the cards rendered by `settings/company/Show.vue`.
 */
export type CompanySection =
    'identity' | 'contact' | 'address' | 'fiscal' | 'socials';

/** A label/value pair rendered by `CompanyDetailList`. */
export type CompanyDetail = {
    label: string;
    value: string | null;
    /** Renders the value as a link — `mailto:`, `tel:` or an absolute URL. */
    href?: string | null;
    /** Lets a long free-text value wrap instead of being clipped. */
    multiline?: boolean;
};
