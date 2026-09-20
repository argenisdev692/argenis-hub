<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\CvJobStudio\Application\DTOs\StudioOwnRateData;
use Modules\CvJobStudio\Domain\Ports\StudioApplicationRepositoryPort;
use Modules\CvJobStudio\Domain\Services\BetaBinomialEstimator;
use Modules\CvJobStudio\Domain\Services\ChannelOutcomeTally;

/**
 * Own-rate panel (T-134, FR-48/FR-51, NFR-14): per channel bucket —
 * applications, positives, rate with 90% credible interval, and whether the
 * evidence gate is passed. Display only; never applied below the gate.
 *
 * Cached per user: the tally scans every application, and only
 * `RecordOutcomeHandler` changes its inputs — it forgets the key on write.
 */
final readonly class GetOwnRatesHandler
{
    private const int TTL_MINUTES = 10;

    public function __construct(
        private BetaBinomialEstimator $estimator,
        private ChannelOutcomeTally $outcomes,
        private StudioApplicationRepositoryPort $applications,
        private Cache $cache,
    ) {}

    #[\NoDiscard]
    public static function cacheKey(int $userId): string
    {
        return "cv_studio:own_rates:{$userId}";
    }

    /** @return list<StudioOwnRateData> */
    #[\NoDiscard]
    public function handle(int $userId): array
    {
        return $this->cache->remember(
            self::cacheKey($userId),
            now()->addMinutes(self::TTL_MINUTES),
            fn (): array => $this->compute($userId),
        );
    }

    /** @return list<StudioOwnRateData> */
    private function compute(int $userId): array
    {
        $rows = [];

        foreach ($this->outcomes->tally($this->applications->channelOutcomes($userId)) as $bucket => $counts) {
            $estimate = $this->estimator->estimate(0.0, $counts['positives'], $counts['trials'], 30, 3, 100.0);

            $rows[] = new StudioOwnRateData(
                bucket: (string) $bucket,
                applications: $counts['trials'],
                positives: $counts['positives'],
                rate: $counts['trials'] > 0 ? round($counts['positives'] / $counts['trials'], 3) : null,
                lower: round($estimate['lower'], 3),
                upper: round($estimate['upper'], 3),
                gatePassed: $estimate['applied'],
            );
        }

        return $rows;
    }
}
