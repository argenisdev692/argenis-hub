<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Metrics window (plan §5 `MetricsFilterData`).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class MetricsFilterData extends Data
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $groupBy = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date', 'before_or_equal:to'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'groupBy' => ['nullable', 'string', 'in:channel,variant,country,signal,origin,wave'],
        ];
    }
}
