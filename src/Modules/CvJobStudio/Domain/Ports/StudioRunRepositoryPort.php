<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Modules\CvJobStudio\Application\DTOs\StudioInsightReportData;
use Modules\CvJobStudio\Application\DTOs\StudioRunData;

/** Discovery runs and the insight report each run produces (FR-26…FR-28). */
interface StudioRunRepositoryPort
{
    public function runForUser(string $uuid, int $userId): ?StudioRunData;

    /**
     * What the report reads for one run's profile: one row per (posting,
     * requirement) pair incl. capped/skipped postings, how many postings that
     * is, and every stored score's uncapped values. `covered` marks a
     * requirement some score gave credit to — the CV evidencing it.
     *
     * @return array{run_id: int, rules_version: int, jds_analyzed: int, requirements: list<array{posting_id: int, canonical_name: string, covered: bool}>, scores: list<array{cap_reason: string|null, raw_score: float, total_score: float}>}|null
     */
    public function insightInputs(string $runUuid, int $userId): ?array;

    /** @param  array<string, mixed>  $attributes */
    public function storeInsightReport(array $attributes): StudioInsightReportData;

    public function latestInsightReport(string $runUuid, int $userId): ?StudioInsightReportData;
}
