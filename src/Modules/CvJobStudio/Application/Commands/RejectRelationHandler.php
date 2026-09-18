<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

final readonly class RejectRelationHandler
{
    public function handle(string $uuid, int $userId): void
    {
        $updated = StudioSkillRelationEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->update(['status' => 'rejected']);

        if ($updated === 0) {
            throw new PostingNotFoundException("Relation {$uuid} not found.");
        }
    }
}
