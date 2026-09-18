<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

/** Restores the original source as preferred and records the unlink (T-119). */
final readonly class UnlinkResolutionHandler
{
    public function handle(string $postingUuid, int $userId): void
    {
        $updated = StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $postingUuid)
            ->update(['preferred_source_id' => null, 'apply_destination' => null]);

        if ($updated === 0) {
            throw new PostingNotFoundException("Posting {$postingUuid} not found.");
        }
    }
}
