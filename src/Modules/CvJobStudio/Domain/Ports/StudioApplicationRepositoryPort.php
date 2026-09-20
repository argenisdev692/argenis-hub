<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioApplicationData;

/**
 * Applications, their employer-side outcomes and the per-channel baselines
 * those outcomes blend into. Outcomes adjust opportunity only — fit is never
 * touched from here (NFR-13).
 */
interface StudioApplicationRepositoryPort
{
    /** @return LengthAwarePaginator<int, StudioApplicationData> */
    public function paginate(int $userId, int $perPage): LengthAwarePaginator;

    public function recordOutcome(int $postingId, int $userId, string $outcome, ?string $note): void;

    /** @return list<array{bucket: string|null, outcome: string|null}> */
    public function channelOutcomes(int $userId): array;

    /** @return list<array{requirement: string, outcome: string|null}> one row per (application, requirement) */
    public function requirementOutcomes(int $userId): array;

    /**
     * Row-locks the channel baselines for `$buckets` — call inside a transaction.
     *
     * @param  list<string>  $buckets
     * @return array<string, float> bucket => policy value, only for existing baselines
     */
    public function lockChannelBaselines(int $userId, array $buckets): array;

    /** Stamps `applied_from` / `gate_passed_at` the first time the evidence gate passes. */
    public function updateChannelBaseline(int $userId, string $bucket, int $positives, int $trials, bool $gatePassed): void;
}
