<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Score response. Every number is labelled a heuristic (NFR-3) — the shape
 * carries the breakdown that recomputes the total offline (SC-2).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioScoreData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly float $h,
        public readonly float $s,
        public readonly float $d,
        public readonly float $rawScore,
        public readonly float $totalScore,
        public readonly ?string $band,
        public readonly ?string $capReason,
        public readonly int $rulesVersion,
        public readonly string $heuristicLabel,
        public readonly ?string $computedAt,
    ) {}
}
