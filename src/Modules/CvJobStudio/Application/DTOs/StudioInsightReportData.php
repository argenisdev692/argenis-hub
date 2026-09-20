<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** A run's insight report (Insights page) — allowlist, no internal ids (OWASP §12). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioInsightReportData extends Data
{
    /**
     * @param  array{total: int, requirements: list<array{name: string, count: int, covered: bool, percentage: float|null}>}  $requirements
     * @param  list<array{cap: string, capped_count: int, unlocked_count: int}>  $reachTable
     * @param  list<string>  $recommendations
     * @param  array{suppressed: bool, rows: list<array{requirement: string, answered_rate: float|null, silent_rate: float|null}>}  $outcomeCorrelation
     */
    public function __construct(
        public readonly string $uuid,
        public readonly int $jdsAnalyzed,
        public readonly ?string $sampleNote,
        public readonly array $requirements,
        public readonly array $reachTable,
        public readonly array $recommendations,
        public readonly array $outcomeCorrelation,
        public readonly int $rulesVersion,
        public readonly ?string $createdAt,
    ) {}
}
