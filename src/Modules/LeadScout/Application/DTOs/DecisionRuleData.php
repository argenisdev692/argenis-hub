<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Decision-rule write (plan §5 `DecisionRuleData`): sample, window and
 * rate thresholds. Locked before measuring (FR-19); locked rows are
 * immutable — only a new version for a new period.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class DecisionRuleData extends Data
{
    /**
     * @param  array<string, float>|null  $thresholds
     */
    public function __construct(
        public ?int $sampleSize = null,
        public ?int $windowDays = null,
        public ?array $thresholds = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'sampleSize' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'windowDays' => ['nullable', 'integer', 'min:7', 'max:365'],
            'thresholds' => ['nullable', 'array'],
            'thresholds.scale_at' => ['required_with:thresholds', 'numeric', 'min:0', 'max:1'],
            'thresholds.stop_below' => ['required_with:thresholds', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
