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
     * What the report reads for one run's profile: every requirement name
     * (incl. capped/skipped postings) and every stored score's uncapped values.
     *
     * @return array{run_id: int, rules_version: int, requirement_names: list<string>, scores: list<array{cap_reason: string|null, raw_score: float, total_score: float}>}|null
     */
    public function insightInputs(string $runUuid, int $userId): ?array;

    /** @param  array<string, mixed>  $attributes */
    public function storeInsightReport(array $attributes): StudioInsightReportData;

    public function latestInsightReport(string $runUuid, int $userId): ?StudioInsightReportData;
}
