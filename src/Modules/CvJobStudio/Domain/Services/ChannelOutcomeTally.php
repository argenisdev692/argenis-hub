<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Employer-side outcomes per discovery channel (FR-48). An outcome is
 * *usable* once the employer has answered or gone silent long enough; it is
 * *positive* when it moved the application forward. Single source for the
 * baseline recompute, the own-rate panel and the insight report.
 */
final readonly class ChannelOutcomeTally
{
    /** @var list<string> */
    public const array USABLE = ['no_reply', 'rejected', 'screening', 'interview', 'offer'];

    /** @var list<string> */
    public const array POSITIVE = ['screening', 'interview', 'offer'];

    #[\NoDiscard]
    public function isPositive(?string $outcome): bool
    {
        return in_array($outcome, self::POSITIVE, true);
    }

    /**
     * @param  iterable<array{bucket: string|null, outcome: string|null}>  $applications
     * @return array<string, array{trials: int, positives: int}>
     */
    #[\NoDiscard]
    public function tally(iterable $applications): array
    {
        $byBucket = [];

        foreach ($applications as $application) {
            if (! in_array($application['outcome'], self::USABLE, true)) {
                continue;
            }

            $bucket = $application['bucket'] ?? 'unknown';
            $byBucket[$bucket] ??= ['trials' => 0, 'positives' => 0];
            $byBucket[$bucket]['trials']++;
            $byBucket[$bucket]['positives'] += $this->isPositive($application['outcome']) ? 1 : 0;
        }

        return $byBucket;
    }
}
