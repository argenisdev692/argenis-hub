<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Cvs;

use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * Read-only seam over `Modules\Cvs` (T-068, GAP-A1). Neither this module nor
 * any other writes to `cvs` through here.
 */
final readonly class EloquentCvSource implements CvSourcePort
{
    public function primaryForUser(int $userId): ?array
    {
        $cv = CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('is_primary', true)
            ->orderByDesc('created_at')
            ->first(['id', 'raw_text', 'is_primary']);

        return $cv === null ? null : ['cv_id' => $cv->id, 'raw_text' => $cv->raw_text, 'is_primary' => $cv->is_primary];
    }

    public function findForUser(string $cvUuid, int $userId): ?array
    {
        $cv = CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $cvUuid)
            ->first(['id', 'raw_text', 'is_primary']);

        return $cv === null ? null : ['cv_id' => $cv->id, 'raw_text' => $cv->raw_text, 'is_primary' => $cv->is_primary];
    }
}
