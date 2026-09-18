<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\OutcomeCorrelator;
use Modules\CvJobStudio\Domain\Services\ReachCalculator;
use Modules\CvJobStudio\Domain\Services\RequirementFrequencyCounter;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioInsightReportEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

/**
 * Per-run insight report (T-085, FR-26…FR-28): requirement frequency over
 * every posting read (incl. capped/skipped, excl. G2), reach table from
 * stored uncapped values, outcome correlation suppressed below 8 usable
 * outcomes. Always produced — even on zero matches (SC-1). Never proposes
 * adding an absent skill (FR-8): recommendations reference only CV-covered
 * requirements.
 */
final readonly class BuildInsightReportHandler
{
    public function __construct(
        private RequirementFrequencyCounter $frequency,
        private ReachCalculator $reach,
        private OutcomeCorrelator $correlator,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $runUuid, int $userId): StudioInsightReportEloquentModel
    {
        return $this->db->atomic(function () use ($runUuid, $userId): StudioInsightReportEloquentModel {
            $run = StudioRunEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $runUuid)
                ->firstOrFail();

            $requirements = StudioRequirementEloquentModel::query()
                ->where('user_id', $userId)
                ->whereHas('posting', static fn ($q) => $q->where('profile_id', $run->profile_id))
                ->with('posting.scores')
                ->get()
                ->map(static fn ($requirement): array => [
                    'canonical_name' => $requirement->canonical_name,
                    'covered' => false,
                ])
                ->all();

            $frequency = $this->frequency->count($requirements, 30);

            $scores = StudioScoreEloquentModel::query()
                ->where('user_id', $userId)
                ->whereHas('posting', static fn ($q) => $q->where('profile_id', $run->profile_id))
                ->get(['cap_reason', 'raw_score', 'total_score'])
                ->map(static fn ($score): array => [
                    'cap_reason' => $score->cap_reason,
                    'raw_score' => (float) $score->raw_score,
                    'total_score' => (float) $score->total_score,
                ])
                ->all();

            $observations = StudioApplicationEloquentModel::query()
                ->ownedBy($userId)
                ->with('posting.requirements')
                ->get()
                ->flatMap(static fn ($application) => $application->posting->requirements->map(
                    static fn ($requirement): array => [
                        'requirement' => $requirement->canonical_name,
                        'answered' => in_array($application->outcome, ['screening', 'interview', 'offer'], true),
                    ],
                ))
                ->all();

            return StudioInsightReportEloquentModel::query()->create([
                'user_id' => $userId,
                'run_id' => $run->id,
                'jds_analyzed' => count($requirements),
                'sample_note' => count($requirements) < 30 ? 'Sample below 30 — percentages indicative only.' : null,
                'requirements' => $frequency,
                'reach_table' => $this->reach->table($scores, 70.0),
                'recommendations' => [],
                'outcome_correlation' => $this->correlator->compare($observations, 8),
                'rules_version' => $run->rules_version,
            ]);
        });
    }
}
