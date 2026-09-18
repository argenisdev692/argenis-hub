<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Modules\CvJobStudio\Domain\ValueObjects\ScoreBreakdown;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

interface StudioScoreRepositoryPort
{
    public function store(string $postingUuid, int $userId, ScoreBreakdown $breakdown, string $postingTextHash): StudioScoreEloquentModel;

    public function latestForPosting(string $postingUuid, int $userId): ?StudioScoreEloquentModel;
}
