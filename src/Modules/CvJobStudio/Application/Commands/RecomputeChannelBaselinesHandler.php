<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\StudioApplicationRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\BetaBinomialEstimator;
use Modules\CvJobStudio\Domain\Services\ChannelOutcomeTally;

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
        private ChannelOutcomeTally $outcomes,
        private StudioApplicationRepositoryPort $applications,
        private TransactionPort $db,
    ) {}

    public function handle(int $userId): void
    {
        $this->db->atomic(function () use ($userId): void {
            $byBucket = $this->outcomes->tally($this->applications->channelOutcomes($userId));
            $policies = $this->applications->lockChannelBaselines($userId, array_keys($byBucket));

            foreach ($policies as $bucket => $policyValue) {
                $counts = $byBucket[$bucket];
                $estimate = $this->estimator->estimate($policyValue, $counts['positives'], $counts['trials'], 30, 3, 100.0);

                $this->applications->updateChannelBaseline($userId, $bucket, $counts['positives'], $counts['trials'], $estimate['applied']);
            }
        });
    }
}
