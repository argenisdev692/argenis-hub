<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

/**
 * Relations inbox (T-124, FR-44): a model may PROPOSE a relation, but only
 * the candidate's confirmation grants credit. Confirmation is the audited
 * business action (T-091).
 */
final readonly class ConfirmRelationHandler
{
    public function handle(string $uuid, int $userId): void
    {
        $updated = StudioSkillRelationEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->update(['status' => 'confirmed']);

        if ($updated === 0) {
            throw new PostingNotFoundException("Relation {$uuid} not found.");
        }
    }
}
