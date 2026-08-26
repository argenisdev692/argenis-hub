<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Persistence\Mappers;

use App\Models\CompanyData;
use InvalidArgumentException;
use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\Enums\SocialChannel;
use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Modules\Company\Domain\ValueObjects\GeoCoordinates;
use Modules\Company\Domain\ValueObjects\PostalCode;
use Modules\Company\Domain\ValueObjects\WebUrl;

/**
 * The one place that knows both the company_data columns and the Domain shape.
 *
 * A mapper earns its keep here because the two shapes genuinely diverge: six
 * link columns collapse into one channel map, three path columns into one logo
 * map, latitude/longitude into a single paired value object, and the column
 * called "name" is the LEGAL name sitting beside "company_name", which is the
 * trading one — the sort of naming a domain model should not inherit.
 *
 * Reading is forgiving on purpose: legacy rows predate the value objects, and a
 * settings screen that throws because someone once saved a scheme-less URL is
 * useless precisely when it is needed to fix that value. Invalid stored values
 * degrade to null on read; writing is strict.
 */
final readonly class CompanyMapper
{
    public static function toSnapshot(CompanyData $model): CompanySnapshot
    {
        return new CompanySnapshot(
            uuid: (string) $model->uuid,
            companyName: (string) $model->company_name,
            legalName: $model->name,
            description: $model->description,
            website: self::url($model->website),
            email: $model->email,
            phone: $model->phone,
            addressLine1: $model->address,
            addressLine2: $model->address_2,
            postalCode: self::postalCode($model->zip_code),
            city: $model->city,
            state: $model->state,
            country: $model->country,
            countryCode: $model->country_code,
            coordinates: self::coordinates($model->latitude, $model->longitude),
            logos: self::logos($model),
            socials: self::socials($model),
            nifNipc: $model->nif_nipc,
            nie: $model->nie,
            bankBeneficiary: $model->bank_beneficiary,
            bankIban: $model->bank_iban,
            bankBic: $model->bank_bic,
            bankName: $model->bank_name,
            invoiceNotes: $model->invoice_notes,
            createdAt: $model->created_at?->toIso8601String(),
            updatedAt: $model->updated_at?->toIso8601String(),
        );
    }

    /**
     * The columns this module owns.
     *
     * user_id, signature_path and the timestamps are absent by design — the
     * repository fills only what is listed here, so a column outside this
     * module scope can never be nulled by an edit that never mentioned it.
     *
     * @return array<string, mixed>
     */
    public static function toColumns(CompanySnapshot $company): array
    {
        $columns = [
            'company_name' => $company->companyName,
            'name' => $company->legalName,
            'description' => $company->description,
            'website' => $company->website?->value,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->addressLine1,
            'address_2' => $company->addressLine2,
            'zip_code' => $company->postalCode?->value,
            'city' => $company->city,
            'state' => $company->state,
            'country' => $company->country,
            'country_code' => $company->countryCode,
            'latitude' => $company->coordinates?->latitude,
            'longitude' => $company->coordinates?->longitude,
            'nif_nipc' => $company->nifNipc,
            'nie' => $company->nie,
            'bank_beneficiary' => $company->bankBeneficiary,
            'bank_iban' => $company->bankIban,
            'bank_bic' => $company->bankBic,
            'bank_name' => $company->bankName,
            'invoice_notes' => $company->invoiceNotes,
        ];

        foreach (LogoVariant::cases() as $variant) {
            $columns[$variant->column()] = $company->logo($variant);
        }

        foreach (SocialChannel::cases() as $channel) {
            $columns[$channel->column()] = $company->social($channel)?->value;
        }

        return $columns;
    }

    /**
     * @return array<string, string|null>
     */
    private static function logos(CompanyData $model): array
    {
        $logos = [];

        foreach (LogoVariant::cases() as $variant) {
            $column = $variant->column();
            $logos[$variant->value] = $model->{$column};
        }

        return $logos;
    }

    /**
     * @return array<string, WebUrl|null>
     */
    private static function socials(CompanyData $model): array
    {
        $socials = [];

        foreach (SocialChannel::cases() as $channel) {
            $column = $channel->column();
            $socials[$channel->value] = self::url($model->{$column});
        }

        return $socials;
    }

    private static function url(?string $value): ?WebUrl
    {
        try {
            return WebUrl::fromNullable($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function postalCode(?string $value): ?PostalCode
    {
        try {
            return PostalCode::fromNullable($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function coordinates(?float $latitude, ?float $longitude): ?GeoCoordinates
    {
        try {
            return GeoCoordinates::fromNullable($latitude, $longitude);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
