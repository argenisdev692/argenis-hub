<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Domain\Services\BetaBinomialEstimator;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;

/**
 * Own-rate panel (T-134, FR-48/FR-51, NFR-14): per channel bucket —
 * applications, positives, rate with 90% credible interval, and whether the
 * evidence gate is passed. Display only; never applied below the gate.
 *
 * @return list<array{bucket: string, applications: int, positives: int, rate: float|null, lower: float, upper: float, gate_passed: bool}>
 */
final readonly class GetOwnRatesHandler
{
    public function __construct(private BetaBinomialEstimator $estimator) {}

    #[\NoDiscard]
    public function handle(int $userId): array
    {
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

        $rows = [];

        foreach ($byBucket as $bucket => $counts) {
            $estimate = $this->estimator->estimate(0.0, $counts['positives'], $counts['trials'], 30, 3, 100.0);

            $rows[] = [
                'bucket' => $bucket,
                'applications' => $counts['trials'],
                'positives' => $counts['positives'],
                'rate' => $counts['trials'] > 0 ? round($counts['positives'] / $counts['trials'], 3) : null,
                'lower' => round($estimate['lower'], 3),
                'upper' => round($estimate['upper'], 3),
                'gate_passed' => $estimate['applied'],
            ];
        }

        return $rows;
    }
}
