<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;

/** Drops a mis-extracted requirement so the next rescore excludes it (T-067). */
final readonly class DismissRequirementHandler
{
    public function __construct(private ScorePostingHandler $rescore) {}

    public function handle(string $requirementUuid, int $userId): void
    {
        $deleted = StudioRequirementEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $requirementUuid)
            ->delete();

        if ($deleted === 0) {
            throw new PostingNotFoundException("Requirement {$requirementUuid} not found.");
        }
    }
}
