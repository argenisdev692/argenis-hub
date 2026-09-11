<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Retry request (E6). Corrected ranges are accepted only when the edit failed
 * with `invalid_cut_ranges` (P1) — enforced by the retry handler.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class RetryVideoEditData extends Data
{
    /**
     * @param  list<ManualRangeData>|null  $manualRanges
     */
    public function __construct(
        #[DataCollectionOf(ManualRangeData::class)]
        public ?array $manualRanges = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            ...ManualRangeData::listRules('manual_ranges'),
            'manual_ranges' => ['nullable', 'array', 'max:'.(int) config('video-edit.limits.max_manual_ranges')],
        ];
    }
}
