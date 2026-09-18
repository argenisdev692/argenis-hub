<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\OpportunityStatus;
use Modules\LeadScout\Domain\Enums\OpportunityType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Opportunity write (spec US-6, plan §5 `OpportunityData`). Several
 * opportunities can hang off one contact (trial first, then retainer).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpsertOpportunityData extends Data
{
    public function __construct(
        public ?string $type = null,
        public ?int $hoursPerMonth = null,
        public ?int $hourlyRateCents = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?string $status = null,
        public ?string $startedAt = null,
        public ?string $endedAt = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:'.implode(',', OpportunityType::values())],
            'hoursPerMonth' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'hourlyRateCents' => ['nullable', 'integer', 'min:0'],
            'amountCents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', OpportunityStatus::values())],
            'startedAt' => ['nullable', 'date'],
            'endedAt' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
