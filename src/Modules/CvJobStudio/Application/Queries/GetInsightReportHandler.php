<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioInsightReportEloquentModel;

final readonly class GetInsightReportHandler
{
    #[\NoDiscard]
    public function handle(string $runUuid, int $userId): StudioInsightReportEloquentModel
    {
        return StudioInsightReportEloquentModel::query()
            ->where('user_id', $userId)
            ->whereHas('run', static fn ($q) => $q->ownedBy($userId)->where('uuid', $runUuid))
            ->orderByDesc('id')
            ->first()
            ?? throw new PostingNotFoundException("Report for run {$runUuid} not found.");
    }
}
