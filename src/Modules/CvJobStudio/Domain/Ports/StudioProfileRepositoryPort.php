<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/**
 * Profile aggregate: upsert, rules versioning, gate-input validation and run
 * bootstrapping. Profiles own their `rules` snapshot — a run may never
 * silently inherit another profile's gates (FR-31).
 */
interface StudioProfileRepositoryPort
{
    public function paginate(int $userId, int $perPage): LengthAwarePaginator;

    public function findByUuidForUser(string $uuid, int $userId): ?StudioProfileEloquentModel;

    public function upsert(StudioProfileData $data, int $userId, array $defaultRules): StudioProfileEloquentModel;

    public function updateOpportunityRules(string $uuid, int $userId, array $opportunity): StudioProfileEloquentModel;

    public function beginRun(string $uuid, int $userId): StudioRunEloquentModel;
}
