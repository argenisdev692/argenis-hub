<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioSkillRelationRepositoryPort;

/** A rejected proposal never earns credit (T-124, FR-44). */
final readonly class RejectRelationHandler
{
    public function __construct(private StudioSkillRelationRepositoryPort $relations) {}

    public function handle(string $uuid, int $userId): void
    {
        if (! $this->relations->setStatus($uuid, $userId, 'rejected')) {
            throw RecordNotFoundException::of('relation', $uuid);
        }
    }
}
