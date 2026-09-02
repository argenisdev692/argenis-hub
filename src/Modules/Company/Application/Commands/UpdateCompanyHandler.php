<?php

declare(strict_types=1);

namespace Modules\Company\Application\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Company\Application\DTOs\CompanyProfileData;
use Modules\Company\Application\DTOs\UpdateCompanyData;
use Modules\Company\Domain\Enums\SocialChannel;
use Modules\Company\Domain\Events\CompanyCountryChanged;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;
use Modules\Company\Domain\ValueObjects\GeoCoordinates;
use Modules\Company\Domain\ValueObjects\PostalCode;
use Modules\Company\Domain\ValueObjects\WebUrl;

/**
 * Applies an edit to the singleton company record.
 *
 * No `AuditPort` call here, and that is deliberate rather than an omission. This
 * is a plain attribute update, which is precisely what the `LogsActivity` trait
 * on the model already records — with `logOnlyDirty()`, under the `company.data`
 * log name, including before/after values. Adding a manual entry would write a
 * second, weaker row for the same event and would copy the bank block into an
 * extra location. The explicit audit trail belongs to
 * {@see UpdateCompanyLogosHandler}, where an upload and a deletion happen that
 * the attribute diff cannot describe.
 */
final readonly class UpdateCompanyHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private CompanyLogoStoragePort $logos,
        private Dispatcher $events,
    ) {}

    #[\NoDiscard('handle() returns the updated company profile.')]
    public function handle(UpdateCompanyData $data): CompanyProfileData
    {
        $current = $this->companies->current();

        $updated = $current
            ->with($this->changesFrom($data))
            |> $this->companies->save(...);

        // Only a real relocation is announced — re-saving the same code is not a
        // country change, and downstream consumers (Availability rebuilds the
        // national holidays it materialised) must not be woken by a no-op edit.
        if ($current->countryCode !== $updated->countryCode) {
            $this->events->dispatch(new CompanyCountryChanged($current->countryCode, $updated->countryCode));
        }

        return CompanyProfileData::fromSnapshot($updated, $this->logos->urls($updated->logos));
    }

    /**
     * Translate the validated request into snapshot properties.
     *
     * Blank strings collapse to `null` on the way in: an operator clearing a
     * field submits `''`, and storing that would leave the column "set but
     * empty", which every consumer then has to test for on top of `null`.
     *
     * @return array<string, mixed>
     */
    private function changesFrom(UpdateCompanyData $data): array
    {
        return [
            'companyName' => (string) self::text($data->companyName),
            'legalName' => self::text($data->legalName),
            'description' => self::paragraph($data->description),
            'website' => WebUrl::fromNullable($data->website),
            'email' => self::email($data->email),
            'phone' => self::text($data->phone),
            'addressLine1' => self::text($data->address),
            'addressLine2' => self::text($data->address2),
            'postalCode' => PostalCode::fromNullable($data->zipCode),
            'city' => self::text($data->city),
            'state' => self::text($data->state),
            'country' => self::text($data->country),
            'countryCode' => self::countryCode($data->countryCode),
            'coordinates' => GeoCoordinates::fromNullable($data->latitude, $data->longitude),
            'socials' => self::socials($data),
            'nifNipc' => self::text($data->nifNipc),
            'nie' => self::text($data->nie),
            'bankBeneficiary' => self::text($data->bankBeneficiary),
            'bankIban' => self::iban($data->bankIban),
            'bankBic' => self::countryCode($data->bankBic),
            'bankName' => self::text($data->bankName),
            'invoiceNotes' => self::paragraph($data->invoiceNotes),
        ];
    }

    /**
     * @return array<string, WebUrl|null>
     */
    private static function socials(UpdateCompanyData $data): array
    {
        $links = [
            SocialChannel::Facebook->value => $data->facebookLink,
            SocialChannel::Github->value => $data->githubLink,
            SocialChannel::Instagram->value => $data->instagramLink,
            SocialChannel::Linkedin->value => $data->linkedinLink,
            SocialChannel::Tiktok->value => $data->tiktokLink,
            SocialChannel::Twitter->value => $data->twitterLink,
        ];

        return array_map(WebUrl::fromNullable(...), $links);
    }

    /**
     * Single-line text: trimmed, internal runs of whitespace collapsed.
     */
    private static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $value
            |> trim(...)
            |> (static fn (string $text): string => (string) preg_replace('/\s+/u', ' ', $text));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Multi-line text: trimmed at the ends only, so the deliberate line breaks
     * in a description or an invoice footnote survive.
     */
    private static function paragraph(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private static function email(?string $value): ?string
    {
        return self::text($value) |> (static fn (?string $email): ?string => $email === null
            ? null
            : mb_strtolower($email));
    }

    /**
     * ISO country codes and BIC/SWIFT codes are upper-case by convention, and
     * comparing them anywhere downstream is only safe if they are stored that way.
     */
    private static function countryCode(?string $value): ?string
    {
        return self::text($value) |> (static fn (?string $code): ?string => $code === null
            ? null
            : mb_strtoupper($code));
    }

    /**
     * IBANs are quoted with grouping spaces on paper and without them in
     * payment files. Store the machine form; the UI can re-group for display.
     */
    private static function iban(?string $value): ?string
    {
        return self::text($value) |> (static fn (?string $iban): ?string => $iban === null
            ? null
            : mb_strtoupper(str_replace(' ', '', $iban)));
    }
}
