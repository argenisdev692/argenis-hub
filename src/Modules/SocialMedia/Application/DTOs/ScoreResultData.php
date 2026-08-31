<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One of the five quality scores the generation agent reports per attempt.
 * `factors` breaks the value down (e.g. hook_strength, shareability) for the
 * frontend score panel; `explanation` is shown to the user and fed back into
 * the next iteration's prompt when this score fails its threshold.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ScoreResultData extends Data
{
    /**
     * @param  array<string, int>  $factors
     */
    public function __construct(
        public int $value,
        public int $threshold,
        public bool $passes,
        public array $factors,
        public string $explanation,
    ) {}
}
