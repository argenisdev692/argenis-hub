<?php

declare(strict_types=1);

namespace Modules\Company\Application\DTOs;

use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\Enums\SocialChannel;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The company as the authenticated operator sees it.
 *
 * Flat on purpose, unlike {@see PublicCompanyData}: this is the shape the edit
 * form binds to, and a flat object maps one-to-one onto the fields the operator
 * fills in. The two shapes differ because the two audiences differ — the admin
 * screen needs the fiscal and bank block, the public API must never see it.
 *
 * `id` and `user_id` are absent by omission; `logos` carries resolved URLs
 * rather than storage keys.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyProfileData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $companyName,
        public readonly ?string $legalName,
        public readonly ?string $description,
        public readonly ?string $website,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $address,
        #[MapOutputName('address_2')]
        public readonly ?string $address2,
        public readonly ?string $zipCode,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $nifNipc,
        public readonly ?string $nie,
        public readonly ?string $bankBeneficiary,
        public readonly ?string $bankIban,
        public readonly ?string $bankBic,
        public readonly ?string $bankName,
        public readonly ?string $invoiceNotes,
        public readonly ?string $facebookLink,
        public readonly ?string $githubLink,
        public readonly ?string $instagramLink,
        public readonly ?string $linkedinLink,
        public readonly ?string $tiktokLink,
        public readonly ?string $twitterLink,
        public readonly CompanyLogosData $logos,
        public readonly ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, string>  $logoUrls  {@see LogoVariant} value → absolute URL
     */
    public static function fromSnapshot(CompanySnapshot $company, array $logoUrls): self
    {
        return new self(
            uuid: $company->uuid,
            companyName: $company->companyName,
            legalName: $company->legalName,
            description: $company->description,
            website: $company->website?->value,
            email: $company->email,
            phone: $company->phone,
            address: $company->addressLine1,
            address2: $company->addressLine2,
            zipCode: $company->postalCode?->value,
            city: $company->city,
            state: $company->state,
            country: $company->country,
            countryCode: $company->countryCode,
            latitude: $company->coordinates?->latitude,
            longitude: $company->coordinates?->longitude,
            nifNipc: $company->nifNipc,
            nie: $company->nie,
            bankBeneficiary: $company->bankBeneficiary,
            bankIban: $company->bankIban,
            bankBic: $company->bankBic,
            bankName: $company->bankName,
            invoiceNotes: $company->invoiceNotes,
            facebookLink: $company->social(SocialChannel::Facebook)?->value,
            githubLink: $company->social(SocialChannel::Github)?->value,
            instagramLink: $company->social(SocialChannel::Instagram)?->value,
            linkedinLink: $company->social(SocialChannel::Linkedin)?->value,
            tiktokLink: $company->social(SocialChannel::Tiktok)?->value,
            twitterLink: $company->social(SocialChannel::Twitter)?->value,
            logos: CompanyLogosData::fromUrls($logoUrls),
            updatedAt: $company->updatedAt,
        );
    }
}
