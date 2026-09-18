<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\BetaBinomialEstimator;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioChannelBaselineEloquentModel;

/**
 * Blends own outcomes into channel baselines past the evidence gate
 * (T-132, FR-48). NEVER writes total_score, raw_score or any fit component
 * (NFR-13 — asserted by test). SC-14: the 16 historical applications leave
 * every factor at policy.
 */
final readonly class RecomputeChannelBaselinesHandler
{
    public function __construct(
        private BetaBinomialEstimator $estimator,
        private TransactionPort $db,
    ) {}

    public function handle(int $userId): void
    {
        $this->db->atomic(function () use ($userId): void {
            $outcomes = StudioApplicationEloquentModel::query()
                ->ownedBy($userId)
                ->with('posting:id,discovery_channel')
                ->get();

            $byBucket = [];

            foreach ($outcomes as $application) {
                $bucket = $application->posting->discovery_channel ?? 'unknown';
                $usable = in_array($application->outcome, ['no_reply', 'rejected', 'screening', 'interview', 'offer'], true);

                if (! $usable) {
                    continue;
                }

                $byBucket[$bucket]['trials'] = ($byBucket[$bucket]['trials'] ?? 0) + 1;
                $byBucket[$bucket]['positives'] = ($byBucket[$bucket]['positives'] ?? 0)
                    + (in_array($application->outcome, ['screening', 'interview', 'offer'], true) ? 1 : 0);
            }

            foreach ($byBucket as $bucket => $counts) {
                $baseline = StudioChannelBaselineEloquentModel::query()
                    ->where('user_id', $userId)
                    ->where('bucket_kind', 'channel')
                    ->where('bucket', $bucket)
                    ->lockForUpdate()
                    ->first();

                if ($baseline === null) {
                    continue;
                }

                $estimate = $this->estimator->estimate(
                    (float) $baseline->policy_value,
                    $counts['positives'],
                    $counts['trials'],
                    30,
                    3,
                    100.0,
                );

                $baseline->update([
                    'positives' => $counts['positives'],
                    'trials' => $counts['trials'],
                    'applied_from' => $estimate['applied'] ? now() : $baseline->applied_from,
                    'gate_passed_at' => $estimate['applied'] ? now() : $baseline->gate_passed_at,
                ]);
            }
        });
    }
}
