<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;

/** A structure is unusable until the candidate confirms it (T-069, RK-6). */
final readonly class ConfirmCvStructureHandler
{
    public function handle(string $uuid, int $userId): void
    {
        $updated = StudioCvStructureEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->update(['confirmed_at' => now()]);

        if ($updated === 0) {
            throw new PostingNotFoundException("Structure {$uuid} not found.");
        }
    }
}
