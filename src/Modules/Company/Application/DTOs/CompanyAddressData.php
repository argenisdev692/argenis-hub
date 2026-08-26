<?php

declare(strict_types=1);

namespace Modules\Company\Application\DTOs;

use Modules\Company\Domain\ValueObjects\CompanySnapshot;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The company address for public consumption.
 *
 * `formatted` is composed here rather than on each consumer so the landing
 * pages, the PDF header and the email footer cannot drift into three different
 * comma conventions. Empty parts are dropped, so a company with no state renders
 * without a dangling separator.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyAddressData extends Data
{
    public function __construct(
        #[MapOutputName('line_1')]
        public readonly ?string $line1,

        #[MapOutputName('line_2')]
        public readonly ?string $line2,
        public readonly ?string $zipCode,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $formatted,
    ) {}

    public static function fromSnapshot(CompanySnapshot $company): self
    {
        return new self(
            line1: $company->addressLine1,
            line2: $company->addressLine2,
            zipCode: $company->postalCode?->value,
            city: $company->city,
            state: $company->state,
            country: $company->country,
            countryCode: $company->countryCode,
            latitude: $company->coordinates?->latitude,
            longitude: $company->coordinates?->longitude,
            formatted: self::compose($company),
        );
    }

    /**
     * Collapse the address columns into one presentational line.
     *
     * The postal code and city belong on the same segment ("6200-386 Covilhã"),
     * every other part gets its own.
     */
    private static function compose(CompanySnapshot $company): ?string
    {
        $locality = [$company->postalCode?->value, $company->city]
            |> array_filter(...)
            |> (static fn (array $parts): string => implode(' ', $parts));

        $line = [
            $company->addressLine1,
            $company->addressLine2,
            $locality,
            $company->state,
            $company->country,
        ]
            |> (static fn (array $parts): array => array_filter(
                $parts,
                static fn (?string $part): bool => $part !== null && trim($part) !== '',
            ))
            |> (static fn (array $parts): string => implode(', ', $parts));

        return $line === '' ? null : $line;
    }
}
