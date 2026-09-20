<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;

/** A structure is unusable until the candidate confirms it (T-069, RK-6). */
final readonly class ConfirmCvStructureHandler
{
    public function __construct(private StudioCvRepositoryPort $cvs) {}

    public function handle(string $uuid, int $userId): void
    {
        if (! $this->cvs->confirmStructure($uuid, $userId)) {
            throw RecordNotFoundException::of('structure', $uuid);
        }
    }
}
