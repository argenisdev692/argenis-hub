<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
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

    public static function fromModel(StudioScoreEloquentModel $score): self
    {
        return new self(
            uuid: $score->uuid,
            h: (float) $score->h,
            s: (float) $score->s,
            d: (float) $score->d,
            rawScore: (float) $score->raw_score,
            totalScore: (float) $score->total_score,
            band: $score->band,
            capReason: $score->cap_reason,
            rulesVersion: $score->rules_version,
            heuristicLabel: 'Heuristic estimate — not a vendor ATS score.',
            computedAt: $score->computed_at?->toIso8601String(),
        );
    }
}
