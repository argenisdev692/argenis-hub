<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;

/**
 * Edit an existing profile (T-018 follow-up).
 *
 * The profile could only be changed through `UpsertProfileHandler`, which
 * matches on the slug the form submits. In practice that made editing an
 * accident: the operator had to reproduce the exact slug or a second profile
 * appeared instead of the intended change. This addresses the row by the uuid
 * in the route and returns a real 404 when it is not the caller's.
 */
final readonly class UpdateProfileHandler
{
    public function __construct(private StudioProfileRepositoryPort $profiles) {}

    public function handle(StudioProfileData $data, string $uuid, int $userId): void
    {
        $updated = $this->profiles->updateByUuid($data, $uuid, $userId, (array) config('cv-job-studio'));

        if (! $updated) {
            throw RecordNotFoundException::of('profile', $uuid);
        }
    }
}
