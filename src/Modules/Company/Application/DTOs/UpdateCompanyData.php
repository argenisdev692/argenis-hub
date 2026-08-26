<?php

declare(strict_types=1);

namespace Modules\Company\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The editable surface of the company record.
 *
 * Authoritative validation: the Vue form mirrors these rules with Zod for
 * feedback, but nothing reaches the database that did not pass through here
 * first. Resolved straight into the controller action, so Scramble documents the
 * request body from these property types.
 *
 * Two fields deserve a note:
 *
 * - **`zip_code`** is a plain optional string, not a country-aware format check.
 *   The form pre-fills it from the Google Places selection and locks it, but the
 *   operator can unlock and correct it — Places often returns no postal code for
 *   a street-level match, and sometimes returns one belonging to the adjoining
 *   district. The backend has no better source of truth than the person holding
 *   the utility bill, so it accepts the override and records it in the audit
 *   trail. Normalization happens once, in `PostalCode`.
 * - **`latitude` / `longitude`** never appear in the form; they ride along as
 *   hidden inputs written by the same Places selection. They are bounds-checked
 *   here and again in `GeoCoordinates`, because an unattended field is exactly
 *   the one nobody notices going wrong.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class UpdateCompanyData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $companyName,

        #[Max(255)]
        public readonly ?string $legalName = null,

        #[Max(5000)]
        public readonly ?string $description = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $website = null,

        #[Email, Max(255)]
        public readonly ?string $email = null,

        #[Max(50)]
        public readonly ?string $phone = null,

        #[Max(500)]
        public readonly ?string $address = null,

        #[MapName('address_2'), Max(500)]
        public readonly ?string $address2 = null,

        #[Max(20)]
        public readonly ?string $zipCode = null,

        #[Max(255)]
        public readonly ?string $city = null,

        #[Max(255)]
        public readonly ?string $state = null,

        #[Max(255)]
        public readonly ?string $country = null,

        #[Size(2)]
        public readonly ?string $countryCode = null,

        #[Numeric, Between(-90, 90)]
        public readonly ?float $latitude = null,

        #[Numeric, Between(-180, 180)]
        public readonly ?float $longitude = null,

        #[Max(50)]
        public readonly ?string $nifNipc = null,

        #[Max(50)]
        public readonly ?string $nie = null,

        #[Max(255)]
        public readonly ?string $bankBeneficiary = null,

        #[Max(64)]
        public readonly ?string $bankIban = null,

        #[Max(16)]
        public readonly ?string $bankBic = null,

        #[Max(255)]
        public readonly ?string $bankName = null,

        #[Max(5000)]
        public readonly ?string $invoiceNotes = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $facebookLink = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $githubLink = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $instagramLink = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $linkedinLink = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $tiktokLink = null,

        #[Url(['http', 'https']), Max(255)]
        public readonly ?string $twitterLink = null,
    ) {}
}
