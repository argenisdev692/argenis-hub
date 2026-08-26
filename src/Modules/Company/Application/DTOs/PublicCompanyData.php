<?php

declare(strict_types=1);

namespace Modules\Company\Application\DTOs;

use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The company as the outside world may see it.
 *
 * Consumed by the standalone Astro landing sites over `GET /api/public/company`,
 * which is why it is a hand-picked allowlist rather than a filtered version of
 * the admin shape: the same row also holds the fiscal identity (`nif_nipc`,
 * `nie`), the bank details (`bank_iban`, `bank_bic`, `bank_beneficiary`), the
 * invoice footnotes and the owner's `user_id`. None of those may ever cross this
 * boundary, and an allowlist keeps that true when a column is added later —
 * the new column is absent by default instead of leaking by default (OWASP §12).
 *
 * Everything here is already published on the company's own pages: the trading
 * name, the brand marks, the social profiles and the street address.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PublicCompanyData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $legalName,
        public readonly ?string $description,
        public readonly ?string $website,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly CompanyLogosData $logos,
        public readonly CompanySocialsData $socials,
        public readonly CompanyAddressData $address,
    ) {}

    /**
     * @param  array<string, string>  $logoUrls  {@see LogoVariant} value → absolute URL
     */
    public static function fromSnapshot(CompanySnapshot $company, array $logoUrls): self
    {
        return new self(
            name: $company->companyName,
            legalName: $company->legalName,
            description: $company->description,
            website: $company->website?->value,
            email: $company->email,
            phone: $company->phone,
            logos: CompanyLogosData::fromUrls($logoUrls),
            socials: CompanySocialsData::fromSnapshot($company),
            address: CompanyAddressData::fromSnapshot($company),
        );
    }
}
