<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared bandeja/export filter (plan §5, BACKEND-PHP §5.2 shape adapted).
 *
 * One deliberate deviation: no `status` (active/deleted) — `scout_*` rows
 * are never soft-deleted (suppression + tier are the lifecycle), so `tier[]`
 * plays the lifecycle role the canonical `status` plays elsewhere. The
 * single `scopeApplyFilters` on the company model serves list and export
 * alike (DRY).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class LeadFilterData extends Data
{
    /**
     * @param  list<string>|null  $tier
     * @param  list<string>|null  $country
     * @param  list<string>|null  $companyType
     * @param  list<string>|null  $signalType
     * @param  list<string>|null  $stage
     * @param  list<string>|null  $origin
     */
    public function __construct(
        public ?array $tier = null,
        public ?array $country = null,
        public ?array $companyType = null,
        public ?array $signalType = null,
        public ?array $stage = null,
        public ?array $origin = null,
        public ?bool $needsResearch = null,
        public ?string $search = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $perPage = 15,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'tier' => ['nullable', 'array', 'max:5'],
            'tier.*' => ['string', 'in:A,B,C,discarded'],
            'country' => ['nullable', 'array', 'max:30'],
            'country.*' => ['string', 'size:2'],
            'companyType' => ['nullable', 'array', 'max:10'],
            'companyType.*' => ['string', 'in:'.implode(',', CompanyType::values())],
            'signalType' => ['nullable', 'array', 'max:10'],
            'signalType.*' => ['string'],
            'stage' => ['nullable', 'array', 'max:15'],
            'stage.*' => ['string', 'in:'.implode(',', OutreachStage::values())],
            'origin' => ['nullable', 'array', 'max:5'],
            'origin.*' => ['string', 'in:'.implode(',', CompanyOrigin::values())],
            'needsResearch' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'dateFrom' => ['nullable', 'date', 'before_or_equal:date_to'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:date_from'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
