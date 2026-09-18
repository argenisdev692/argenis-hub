<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

final readonly class GetRunHandler
{
    #[\NoDiscard]
    public function handle(string $uuid, int $userId): StudioRunEloquentModel
    {
        return StudioRunEloquentModel::query()
            ->ownedBy($userId)
            ->with(['profile:uuid,name,slug', 'insightReports'])
            ->where('uuid', $uuid)
            ->first()
            ?? throw new PostingNotFoundException("Run {$uuid} not found.");
    }
}
