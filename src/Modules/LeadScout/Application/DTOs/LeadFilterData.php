<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\ValueObjects\LeadCriteria;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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

    #[\NoDiscard]
    public function toCriteria(): LeadCriteria
    {
        return new LeadCriteria(
            tiers: $this->tier === null ? null : array_map(Tier::from(...), $this->tier),
            countries: $this->country,
            companyTypes: $this->companyType === null ? null : array_map(CompanyType::from(...), $this->companyType),
            signalDimensions: $this->signalType === null ? null : array_map(SignalDimension::from(...), $this->signalType),
            stages: $this->stage === null ? null : array_map(OutreachStage::from(...), $this->stage),
            origins: $this->origin === null ? null : array_map(CompanyOrigin::from(...), $this->origin),
            needsResearch: $this->needsResearch,
            search: $this->search === null || trim($this->search) === '' ? null : trim($this->search),
            createdFrom: $this->dateFrom === null ? null : CarbonImmutable::parse($this->dateFrom)->startOfDay(),
            createdTo: $this->dateTo === null ? null : CarbonImmutable::parse($this->dateTo)->endOfDay(),
        );
    }

    /**
     * The date pair is only cross-checked when both bounds arrive: a lone
     * `date_from` / `date_to` is a valid single-bound search (BACKEND-PHP
     * §5.2), and `before_or_equal:date_to` against a missing field would
     * reject it.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(ValidationContext $context): array
    {
        $isGiven = static fn (mixed $value): bool => is_string($value) && trim($value) !== '';
        $hasBothBounds = $isGiven($context->fullPayload['date_from'] ?? null)
            && $isGiven($context->fullPayload['date_to'] ?? null);

        return [
            'tier' => ['nullable', 'array', 'max:5'],
            'tier.*' => ['string', 'in:'.implode(',', Tier::values())],
            'country' => ['nullable', 'array', 'max:30'],
            'country.*' => ['string', 'size:2'],
            'companyType' => ['nullable', 'array', 'max:10'],
            'companyType.*' => ['string', 'in:'.implode(',', CompanyType::values())],
            'signalType' => ['nullable', 'array', 'max:10'],
            'signalType.*' => ['string', 'in:'.implode(',', SignalDimension::values())],
            'stage' => ['nullable', 'array', 'max:15'],
            'stage.*' => ['string', 'in:'.implode(',', OutreachStage::values())],
            'origin' => ['nullable', 'array', 'max:5'],
            'origin.*' => ['string', 'in:'.implode(',', CompanyOrigin::values())],
            'needsResearch' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'dateFrom' => ['nullable', 'date', ...($hasBothBounds ? ['before_or_equal:date_to'] : [])],
            'dateTo' => ['nullable', 'date', ...($hasBothBounds ? ['after_or_equal:date_from'] : [])],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
