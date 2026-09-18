<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * AI settings screen payload (plan §5 `AiSettingsData`): current value per
 * purpose plus the catalog options feeding both the screen and the draft
 * provider/model selector.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiSettingsData extends Data
{
    /**
     * @param  array<string, array{provider: ?string, model: ?string, fallback_provider: ?string, fallback_model: ?string, options: list<array{provider: string, model: string, label: string, available: bool, unavailable_reason: ?string, est_cost_per_100_usd: float, price_expired: bool}>}>  $purposes
     */
    public function __construct(
        public readonly array $purposes,
    ) {}
}
