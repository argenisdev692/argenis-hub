<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

/**
 * Creates a profile or updates the one with the same slug for this user.
 * A new profile seeds `rules` from the config defaults; an update never
 * overwrites `rules` silently — rules changes are explicit and versioned.
 */
final readonly class UpsertProfileHandler
{
    public function __construct(private StudioProfileRepositoryPort $profiles) {}

    #[\NoDiscard]
    public function handle(StudioProfileData $data, int $userId): StudioProfileEloquentModel
    {
        return $this->profiles->upsert($data, $userId, (array) config('cv-job-studio'));
    }
}
