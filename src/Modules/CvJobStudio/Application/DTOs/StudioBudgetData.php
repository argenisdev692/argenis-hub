<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** Spend vs limit for one category in the current period (FR-33). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioBudgetData extends Data
{
    public function __construct(
        public readonly string $category,
        public readonly int $limitMicros,
        public readonly int $spentMicros,
    ) {}
}
