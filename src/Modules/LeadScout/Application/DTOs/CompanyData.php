<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Company snapshot for intake responses (spec FR-20/FR-17). The full
 * bandeja detail (`LeadDetailData` with score, reasons, decisors, channels)
 * lands in Phase H — this stays a creation receipt on purpose.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $domain,
        public readonly ?string $country,
        public readonly string $origin,
        public readonly ?string $companyType,
        public readonly ?string $createdAt,
    ) {}

    public static function fromModel(ScoutCompanyEloquentModel $company): self
    {
        return new self(
            uuid: $company->uuid,
            name: $company->name,
            domain: $company->canonical_domain,
            country: $company->country,
            origin: $company->origin->value,
            companyType: $company->company_type?->value,
            createdAt: $company->created_at?->toIso8601String(),
        );
    }
}
