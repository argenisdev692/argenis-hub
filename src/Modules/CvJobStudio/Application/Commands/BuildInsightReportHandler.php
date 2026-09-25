<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioInsightReportData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioApplicationRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioRunRepositoryPort;
use Modules\CvJobStudio\Domain\Services\ChannelOutcomeTally;
use Modules\CvJobStudio\Domain\Services\InsightRecommender;
use Modules\CvJobStudio\Domain\Services\OutcomeCorrelator;
use Modules\CvJobStudio\Domain\Services\ReachCalculator;
use Modules\CvJobStudio\Domain\Services\RequirementFrequencyCounter;

/**
 * Per-run insight report (T-085, FR-26…FR-28): requirement frequency over
 * every posting read (incl. capped/skipped, excl. G2), reach table from
 * stored uncapped values, outcome correlation suppressed below 8 usable
 * outcomes. Always produced — even on zero matches (SC-1). Never proposes
 * adding an absent skill (FR-8): {@see InsightRecommender} only ever names a
 * requirement the CV already evidences.
 *
 * `MIN_SAMPLE` is measured in postings analysed, the same unit the frequency
 * percentage is expressed in — a run of 12 jobs says so instead of quietly
 * reporting percentages it cannot support.
 */
final readonly class BuildInsightReportHandler
{
    private const int MIN_SAMPLE = 30;

    private const int MIN_OUTCOMES = 8;

    private const float REACH_THRESHOLD = 70.0;

    public function __construct(
        private RequirementFrequencyCounter $frequency,
        private InsightRecommender $recommender,
        private ReachCalculator $reach,
        private OutcomeCorrelator $correlator,
        private ChannelOutcomeTally $outcomes,
        private StudioRunRepositoryPort $runs,
        private StudioApplicationRepositoryPort $applications,
    ) {}

    #[\NoDiscard]
    public function handle(string $runUuid, int $userId): StudioInsightReportData
    {
        $inputs = $this->runs->insightInputs($runUuid, $userId)
            ?? throw RecordNotFoundException::of('run', $runUuid);

        $observations = array_map(
            fn (array $row): array => ['requirement' => $row['requirement'], 'answered' => $this->outcomes->isPositive($row['outcome'])],
            $this->applications->requirementOutcomes($userId),
        );

        $analyzed = $inputs['jds_analyzed'];
        $frequency = $this->frequency->count($inputs['requirements'], self::MIN_SAMPLE);

        return $this->runs->storeInsightReport([
            'user_id' => $userId,
            'run_id' => $inputs['run_id'],
            'jds_analyzed' => $analyzed,
            'sample_note' => $analyzed < self::MIN_SAMPLE ? 'Sample below 30 postings — percentages indicative only.' : null,
            'requirements' => $frequency,
            'reach_table' => $this->reach->table($inputs['scores'], self::REACH_THRESHOLD),
            'recommendations' => $this->recommender->recommend($frequency),
            'outcome_correlation' => $this->correlator->compare($observations, self::MIN_OUTCOMES),
            'rules_version' => $inputs['rules_version'],
        ]);
    }
}
