<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Application\DTOs\StudioRunData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/** One run's progress counters for the polled Runs page. */
final readonly class GetRunHandler
{
    #[\NoDiscard]
    public function handle(string $uuid, int $userId): StudioRunData
    {
        $run = StudioRunEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->first()
            ?? throw new PostingNotFoundException("Run {$uuid} not found.");

        return new StudioRunData(
            uuid: $run->uuid,
            status: $run->status,
            startedAt: $run->started_at?->toIso8601String(),
            finishedAt: $run->finished_at?->toIso8601String(),
            candidatesCount: (int) $run->candidates_count,
            gatePassedCount: (int) $run->gate_passed_count,
            extractedCount: (int) $run->extracted_count,
            scoredCount: (int) $run->scored_count,
            newMatchesCount: (int) $run->new_matches_count,
            spendMicros: (int) $run->spend_micros,
        );
    }
}
